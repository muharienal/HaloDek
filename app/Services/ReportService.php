<?php

namespace App\Services;

use App\Exceptions\ReportConflict;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportActivity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seluruh logika siklus hidup tiket ada di sini supaya controller tetap tipis
 * dan aturan bisnis (nomor, penugasan, linimasa, notifikasi) konsisten.
 */
class ReportService
{
    public function __construct(
        private NotificationService $notify,
        private AttachmentService $files,
    ) {}

    // ---------------------------------------------------------------- buat

    /**
     * @param  array<UploadedFile>  $files
     */
    public function create(User $user, Team $team, Category $category, array $data, array $files = []): Report
    {
        $report = null;

        // Nomor tiket dibuat di dalam transaksi + retry, jadi dua laporan yang
        // masuk bersamaan tidak akan menghasilkan nomor kembar.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $report = DB::transaction(function () use ($user, $team, $category, $data, $files) {
                    Team::whereKey($team->id)->lockForUpdate()->first();

                    $now = now();
                    $priority = $data['priority'] ?? 'sedang';

                    $report = Report::create([
                        'report_number' => $this->nextNumber($team, $now),
                        'user_id' => $user->id,
                        'reporter_name' => $user->name,
                        'team_id' => $team->id,
                        'category_id' => $category->id,
                        'custom_category' => $category->allow_custom ? ($data['custom_category'] ?? null) : null,
                        'asset_id' => $data['asset_id'] ?? null,
                        'asset_code' => $data['asset_code'] ?? null,
                        'asset_name' => $data['asset_name'] ?? null,
                        'inventory_location_id' => $data['inventory_location_id'] ?? null,
                        'location_name' => $data['location_name'] ?? null,
                        'title' => $data['title'],
                        'description' => $data['description'],
                        'priority' => $priority,
                        'status' => 'menunggu',
                    ]);

                    $this->log($report, $user, 'created', 'Laporan dibuat dan diteruskan ke '.$team->name.'.');
                    $this->files->storeMany($report, $files, 'bukti', $user);

                    return $report;
                });
                break;
            } catch (QueryException $e) {
                // 23000 = pelanggaran unique (nomor kembar) -> coba lagi dengan nomor berikutnya.
                if ($attempt === 5 || ! str_starts_with((string) $e->getCode(), '23')) {
                    throw $e;
                }
            }
        }

        // Laporan sudah tersimpan. Notifikasi tidak boleh membuat pengguna melihat error
        // (yang membuatnya mengirim ulang laporan yang sama).
        try {
            $this->notify->toTeam(
                $team,
                'Laporan baru '.$report->report_number,
                "{$user->name} mengajukan \"{$report->title}\" (prioritas {$report->priorityLabel()}): {$report->summary(80)}",
                $report,
                [$user->id],
            );
            app(WhatsAppNotifier::class)->newReport($report);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Notifikasi laporan baru gagal', ['report' => $report->report_number, 'error' => $e->getMessage()]);
        }

        return $report;
    }

    private function nextNumber(Team $team, Carbon $now): string
    {
        $prefix = $team->code.'-'.$now->format('Ymd').'-';

        $last = Report::withTrashed()
            ->where('report_number', 'like', $prefix.'%')
            ->orderByDesc('report_number')
            ->value('report_number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------- tindak lanjut

    /**
     * Update oleh admin: status, prioritas, petugas, catatan. Hanya perubahan nyata yang dicatat.
     *
     * @param  array{status?:string, priority?:string, assigned_to_id?:int|null, follow_up_note?:?string}  $data
     * @param  array<UploadedFile>  $files
     */
    public function followUp(Report $report, User $actor, array $data, array $files = []): Report
    {
        $waAssignee = null;
        DB::transaction(function () use ($report, $actor, $data, $files, &$waAssignee) {
            $this->lockFresh($report);
            if ($report->status === 'dibatalkan') {
                throw new ReportConflict('Laporan ini sudah dibatalkan pelapor sehingga tidak dapat diubah lagi.');
            }

            $changes = [];
            $now = now();

            // --- prioritas
            if (isset($data['priority']) && $data['priority'] !== $report->priority) {
                $old = $report->priorityLabel();
                $report->priority = $data['priority'];
                $this->log($report, $actor, 'priority', "Prioritas diubah dari {$old} menjadi {$report->priorityLabel()}.");
            }

            // --- penugasan
            if (array_key_exists('assigned_to_id', $data) && (int) $data['assigned_to_id'] !== (int) $report->assigned_to_id) {
                $report->assigned_to_id = $data['assigned_to_id'] ?: null;
                if (! $report->assigned_to_id) {
                    $report->reminded_at = null;
                }
                $assignee = $report->assigned_to_id ? User::find($report->assigned_to_id) : null;
                $this->log($report, $actor, 'assigned', $assignee ? "Penanggung jawab: {$assignee->name}." : 'Penanggung jawab dilepas, laporan kembali menunggu diambil.');
                if ($assignee && $assignee->id !== $actor->id) {
                    $this->notify->toUser($assignee, 'Tugas baru '.$report->report_number, "Anda ditugaskan menangani \"{$report->title}\": {$report->summary(80)}", $report);
                    $waAssignee = $assignee;
                }
                $changes[] = 'assigned';

                // Laporan yang sudah ada penanggung jawabnya otomatis "diproses".
                if ($assignee && $report->status === 'menunggu' && ($data['status'] ?? 'menunggu') === 'menunggu') {
                    $data['status'] = 'diproses';
                }
            }

            // --- status
            $statusChanged = isset($data['status']) && $data['status'] !== $report->status;
            if ($statusChanged) {
                $old = $report->statusLabel();
                $report->status = $data['status'];
                if ($report->status !== 'ditolak') {
                    $report->duplicate_of_id = null; // dibuka/diselesaikan sendiri: bukan laporan ganda lagi
                }

                if ($report->status === 'selesai') {
                    $report->resolved_at = $now;
                    $report->satisfied = null; // pelapor ditanya lagi "sudah beres?"
                } elseif ($report->status === 'ditolak') {
                    $report->rejected_at = $now; // awal batas waktu banding
                } elseif (in_array($report->status, Report::OPEN_STATUSES, true)) {
                    $report->resolved_at = null; // dibuka kembali
                }

                $note = trim((string) ($data['follow_up_note'] ?? ''));
                $this->log($report, $actor, 'status', "Status diubah dari {$old} menjadi {$report->statusLabel()}.", $note !== '' ? ['note' => $note] : null);
            }

            // --- jaga status dan penanggung jawab tetap selaras:
            //     "Menunggu" = belum ada yang memegang; PJ dilepas saat "Diproses" = kembali menunggu.
            if ($report->status === 'menunggu' && $report->assigned_to_id && in_array('assigned', $changes, true)) {
                $report->status = 'diproses'; // baru saja ditugaskan ke seseorang: berarti sedang diproses
                $statusChanged = $report->isDirty('status');
            } elseif ($report->status === 'menunggu' && $report->assigned_to_id) {
                $report->assigned_to_id = null;
                $report->reminded_at = null;
                $this->log($report, $actor, 'released', 'Penanggung jawab dilepas karena status dikembalikan ke Menunggu.');
            } elseif ($report->status === 'diproses' && ! $report->assigned_to_id && in_array('assigned', $changes, true)) {
                $report->status = 'menunggu';
                $statusChanged = $report->isDirty('status');
            }

            // --- catatan tanpa perubahan status tetap tersimpan sebagai update
            $note = trim((string) ($data['follow_up_note'] ?? ''));
            if ($note !== '') {
                $report->follow_up_note = $note;
                if (! $statusChanged) {
                    $this->log($report, $actor, 'comment', $note);
                }
            }

            if (! $report->first_response_at && ($statusChanged || $note !== '' || $changes)) {
                $report->first_response_at = $now;
            }

            $report->save();
            $this->files->storeMany($report, $files, 'penyelesaian', $actor);

            // WA penugasan setelah semua tersimpan; penanggung jawab yang langsung dilepas lagi tidak dikabari.
            if ($waAssignee && (int) $report->assigned_to_id === $waAssignee->id) {
                app(WhatsAppNotifier::class)->assigned($report, $waAssignee, $actor);
            }

            // Staf yang menutup laporan: kepala regu ikut diberi tahu.
            if ($statusChanged && $actor->isStaf() && in_array($report->status, ['selesai', 'ditolak'], true)) {
                $this->notify->toHead($report->team, 'Laporan '.$report->statusLabel().' '.$report->report_number, "{$actor->name} menandai \"{$report->title}\" {$report->statusLabel()}.", $report, [$actor->id]);
            }

            if ($statusChanged && $report->user_id && $report->user_id !== $actor->id) {
                $this->notify->toUser(
                    $report->user_id,
                    'Update laporan '.$report->report_number,
                    "Laporan \"{$report->title}\" kini berstatus {$report->statusLabel()}.".($note !== '' ? " Catatan: {$note}" : ''),
                    $report,
                );
                app(WhatsAppNotifier::class)->reporterUpdate($report);
            }
        });

        return $report->refresh();
    }

    // ------------------------------------------------------------- pindah

    public function transfer(Report $report, User $actor, Team $to, Category $category, ?string $reason): Report
    {
        $from = $report->team;

        DB::transaction(function () use ($report, $actor, $to, $category, $reason, $from) {
            $this->lockFresh($report);
            if (! $report->isOpen()) {
                throw new ReportConflict("Laporan {$report->report_number} sudah {$report->statusLabel()} sehingga tidak bisa dialihkan.");
            }
            $report->team_id = $to->id;
            $report->category_id = $category->id;
            $report->custom_category = null;
            $report->assigned_to_id = null;
            $report->status = 'menunggu';
            $report->resolved_at = null;
            $report->reminded_at = null;
            $report->save();

            $this->log($report, $actor, 'transferred', "Laporan dipindahkan dari {$from->name} ke {$to->name}.".($reason ? " Alasan: {$reason}" : ''));
        });

        $report->load('team');
        $this->notify->toTeam($to, 'Laporan dialihkan '.$report->report_number, "Laporan \"{$report->title}\" dialihkan dari {$from->name}.", $report, [$actor->id]);
        app(WhatsAppNotifier::class)->transferred($report, $actor);

        if ($report->user_id && $report->user_id !== $actor->id) {
            $this->notify->toUser($report->user_id, 'Laporan dialihkan '.$report->report_number, "Laporan Anda dialihkan ke {$to->name} agar ditangani regu yang tepat.", $report);
        }

        return $report;
    }

    // ---------------------------------------------------------- percakapan

    /**
     * @param  array<UploadedFile>  $files
     */
    public function comment(Report $report, User $actor, string $message, bool $internal = false, array $files = []): ReportActivity
    {
        $activity = DB::transaction(function () use ($report, $actor, $message, $internal, $files) {
            $activity = $this->log($report, $actor, 'comment', $message, null, $internal);
            $this->files->storeMany($report, $files, 'komentar', $actor);

            // Balasan pertama dari admin dihitung sebagai respons pertama.
            if ($actor->isAdmin() && ! $report->first_response_at && ! $internal) {
                $report->forceFill(['first_response_at' => now()])->save();
            }

            return $activity;
        });

        if (! $internal) {
            app(WhatsAppNotifier::class)->reply($report, $actor, $message);
            if ($actor->isAdmin()) {
                if ($report->user_id && $report->user_id !== $actor->id) {
                    $this->notify->toUser($report->user_id, 'Balasan baru '.$report->report_number, "{$actor->name}: ".str($message)->limit(120), $report);
                }
            } else {
                $recipients = $report->assigned_to_id
                    ? [$report->assigned_to_id]
                    : null;
                if ($recipients) {
                    $this->notify->toUser($report->assigned_to_id, 'Balasan pelapor '.$report->report_number, "{$actor->name}: ".str($message)->limit(120), $report);
                } else {
                    $this->notify->toTeam($report->team, 'Balasan pelapor '.$report->report_number, "{$actor->name}: ".str($message)->limit(120), $report, [$actor->id]);
                }
            }
        }

        return $activity;
    }

    // ------------------------------------------------------------- batal

    public function cancel(Report $report, User $actor, ?string $reason = null): Report
    {
        DB::transaction(function () use ($report, $actor, $reason) {
            $this->lockFresh($report);
            if ($report->status !== 'menunggu') {
                throw new ReportConflict("Laporan sudah {$report->statusLabel()} oleh petugas sehingga tidak bisa dibatalkan lagi. Kirim balasan bila sudah tidak diperlukan.");
            }
            $report->update(['status' => 'dibatalkan']);
            $this->log($report, $actor, 'cancelled', 'Laporan dibatalkan oleh pelapor.'.($reason ? " Alasan: {$reason}" : ''));
        });

        $this->notify->toTeam($report->team, 'Laporan dibatalkan '.$report->report_number, "{$actor->name} membatalkan laporan \"{$report->title}\".", $report, [$actor->id]);

        return $report;
    }

    // ------------------------------------------- konfirmasi pelapor setelah selesai

    /** Pelapor menyatakan masalah sudah beres. */
    public function confirm(Report $report, User $actor): Report
    {
        DB::transaction(function () use ($report, $actor) {
            $report->forceFill(['satisfied' => true])->save();
            $this->log($report, $actor, 'confirmed', 'Pelapor menyatakan masalah sudah beres.');
        });

        if ($report->assigned_to_id && $report->assigned_to_id !== $actor->id) {
            $this->notify->toUser($report->assigned_to_id, 'Laporan dikonfirmasi beres '.$report->report_number, "{$actor->name} menyatakan \"{$report->title}\" sudah beres.", $report);
        }

        return $report;
    }

    /** Pelapor membuka kembali laporan selesai: kembali ke penanggung jawab (atau antrean regu). */
    public function reopen(Report $report, User $actor, string $reason): Report
    {
        DB::transaction(function () use ($report, $actor, $reason) {
            $report->forceFill([
                'status' => $report->assigned_to_id ? 'diproses' : 'menunggu',
                'resolved_at' => null,
                'follow_up_note' => null, // catatan penyelesaian lama tidak lagi berlaku (tetap tercatat di riwayat)
                'satisfied' => false,
                'reopened_count' => $report->reopened_count + 1,
                'reminded_at' => null,
            ])->save();
            $this->log($report, $actor, 'reopened', 'Laporan dibuka kembali oleh pelapor karena belum beres.', ['note' => $reason]);
        });

        $title = 'Laporan dibuka kembali '.$report->report_number;
        $body = "{$actor->name}: \"".str($reason)->limit(120).'"';
        if ($report->assigned_to_id) {
            $this->notify->toUser($report->assigned_to_id, $title, $body, $report);
            $this->notify->toHead($report->team, $title, $body, $report, [$actor->id, $report->assigned_to_id]);
        } else {
            $this->notify->toTeam($report->team, $title, $body, $report, [$actor->id]);
        }
        app(WhatsAppNotifier::class)->objection($report->refresh(), 'LAPORAN DIBUKA LAGI: BELUM BERES', $reason);

        return $report;
    }

    /**
     * Banding pelapor atas laporan yang ditolak: kembali ke antrean regu dan Kepala Regu diberi tahu.
     * Hanya sekali; penolakan berikutnya bersifat final.
     */
    public function appeal(Report $report, User $actor, string $reason): Report
    {
        DB::transaction(function () use ($report, $actor, $reason) {
            $this->lockFresh($report);
            if (! $report->canStillAppeal()) {
                throw new ReportConflict('Banding untuk laporan ini sudah tidak bisa diajukan.');
            }

            $report->forceFill([
                'status' => 'menunggu',
                'assigned_to_id' => null,
                'appealed_at' => now(),
                'resolved_at' => null,
                'follow_up_note' => null, // alasan penolakan tetap tercatat di riwayat
                'reminded_at' => null,
            ])->save();
            $this->log($report, $actor, 'appealed', 'Pelapor mengajukan banding atas penolakan. Laporan kembali menunggu keputusan Kepala Regu.', ['note' => $reason]);
        });

        $report->loadMissing('team');
        $title = 'Banding laporan '.$report->report_number;
        $this->notify->toHead($report->team, $title, "{$actor->name}: \"".str($reason)->limit(120).'"', $report, [$actor->id]);
        app(WhatsAppNotifier::class)->objection($report->refresh(), 'BANDING LAPORAN DITOLAK', $reason);

        return $report->refresh();
    }

    // --------------------------------------------------------- laporan ganda

    /**
     * Tutup laporan ganda dan tautkan ke laporan utama yang sedang ditangani (status "Ditolak",
     * alasan otomatis, tanpa banding). Pelapor diarahkan ke laporan utama.
     */
    public function markDuplicate(Report $report, User $actor, Report $master): Report
    {
        DB::transaction(function () use ($report, $actor, $master) {
            $this->lockFresh($report);
            if (! $report->isOpen()) {
                throw new ReportConflict("Laporan {$report->report_number} sudah {$report->statusLabel()} sehingga tidak bisa digabung.");
            }
            if ($master->id === $report->id || $master->duplicate_of_id || in_array($master->status, ['ditolak', 'dibatalkan'], true)) {
                throw new ReportConflict("Laporan {$master->report_number} tidak bisa menjadi laporan utama (sudah ditutup atau juga laporan ganda).");
            }

            $note = "Laporan yang sama sudah ada: {$master->report_number}. Penanganan dilanjutkan di laporan itu.";
            $report->forceFill([
                'status' => 'ditolak',
                'duplicate_of_id' => $master->id,
                'assigned_to_id' => null,
                'follow_up_note' => $note,
                'rejected_at' => now(),
                'first_response_at' => $report->first_response_at ?? now(),
            ])->save();
            $this->log($report, $actor, 'merged', "Digabung ke laporan {$master->report_number} karena masalahnya sama.");
            $this->log($master, $actor, 'merged', "Laporan {$report->report_number} dari {$report->reporter_name} digabung ke laporan ini.");
        });

        if ($report->user_id && $report->user_id !== $actor->id) {
            $this->notify->toUser($report->user_id, 'Laporan digabung '.$report->report_number, "Masalah yang sama sudah dilaporkan ({$master->report_number}) dan sedang ditangani.", $report);
            app(WhatsAppNotifier::class)->reporterUpdate($report->refresh());
        }

        return $report->refresh();
    }

    // ------------------------------------------------- ambil / lepas tugas

    /** Petugas mengambil laporan untuk dikerjakan sendiri (status otomatis "diproses"). */
    public function claim(Report $report, User $actor): Report
    {
        DB::transaction(function () use ($report, $actor) {
            // Dua orang menekan "Ambil" bersamaan: baris dikunci lalu dicek ulang,
            // sehingga yang kedua mendapat pesan, bukan menimpa yang pertama.
            $assignedBefore = $report->assigned_to_id;
            $this->lockFresh($report);
            if (! $report->isOpen()) {
                throw new ReportConflict("Laporan {$report->report_number} sudah {$report->statusLabel()}.");
            }
            if ($report->assigned_to_id && $report->assigned_to_id !== $assignedBefore && $report->assigned_to_id !== $actor->id) {
                throw new ReportConflict("Laporan {$report->report_number} baru saja diambil ".($report->assignee->name ?? 'petugas lain').'.');
            }
            if ($report->assigned_to_id === $actor->id) {
                return; // sudah dipegang sendiri
            }

            $previous = $report->assignee;
            $report->assigned_to_id = $actor->id;
            if ($report->status === 'menunggu') {
                $report->status = 'diproses';
            }
            $report->first_response_at ??= now();
            $report->save();

            $this->log($report, $actor, 'claimed', $actor->name.' mengambil laporan ini untuk dikerjakan.'.($previous && $previous->id !== $actor->id ? " (sebelumnya: {$previous->name})" : ''));
        });

        $this->notify->toHead($report->team, 'Laporan diambil '.$report->report_number, "{$actor->name} mengerjakan \"{$report->title}\".", $report, [$actor->id]);

        if ($report->user_id && $report->user_id !== $actor->id) {
            $this->notify->toUser($report->user_id, 'Laporan diproses '.$report->report_number, "Laporan \"{$report->title}\" sedang ditangani {$actor->name}.", $report);
            app(WhatsAppNotifier::class)->reporterUpdate($report->refresh());
        }

        return $report->refresh();
    }

    /** Melepas tugas: laporan kembali ke antrean regu agar bisa diambil/ditugaskan ulang. */
    public function release(Report $report, User $actor, ?string $reason = null): Report
    {
        DB::transaction(function () use ($report, $actor, $reason) {
            $previous = $report->assignee;
            $report->assigned_to_id = null;
            $report->reminded_at = null; // kembali ke antrean: boleh diingatkan lagi bila lewat batas
            if ($report->status === 'diproses') {
                $report->status = 'menunggu';
            }
            $report->save();

            $who = $previous && $previous->id !== $actor->id ? "{$actor->name} melepas {$previous->name} sebagai penanggung jawab." : "{$actor->name} melepas laporan ini, kembali menunggu diambil.";
            $this->log($report, $actor, 'released', $who.($reason ? " Alasan: {$reason}" : ''));
        });

        if ($actor->isStaf()) {
            $this->notify->toHead($report->team, 'Tugas dilepas '.$report->report_number, "{$actor->name} melepas \"{$report->title}\", kembali menunggu diambil.", $report, [$actor->id]);
        }

        return $report->refresh();
    }

    // ------------------------------------------------------------- internal

    /** Kunci baris laporan (sampai transaksi selesai) dan muat ulang datanya dari database. */
    private function lockFresh(Report $report): void
    {
        $fresh = Report::whereKey($report->id)->lockForUpdate()->first();
        if (! $fresh) {
            throw new ReportConflict('Laporan ini sudah dihapus.');
        }
        $report->setRawAttributes($fresh->getAttributes(), true);
        $report->unsetRelation('assignee');
    }

    private function log(Report $report, ?User $user, string $type, ?string $message, ?array $meta = null, bool $internal = false): ReportActivity
    {
        return $report->activities()->create([
            'user_id' => $user?->id,
            'type' => $type,
            'message' => $message,
            'is_internal' => $internal,
            'meta' => $meta,
        ]);
    }
}
