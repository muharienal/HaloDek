<?php

namespace App\Services;

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
                        'inventory_item_id' => $data['inventory_item_id'] ?? null,
                        'location_id' => $data['location_id'] ?? null,
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

        $this->notify->toTeam(
            $team,
            'Laporan baru '.$report->report_number,
            "{$user->name} mengajukan: \"{$report->title}\" (prioritas {$report->priorityLabel()}).",
            $report,
            [$user->id],
        );

        app(WhatsAppNotifier::class)->newReport($report);

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
        DB::transaction(function () use ($report, $actor, $data, $files) {
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
                $assignee = $report->assigned_to_id ? User::find($report->assigned_to_id) : null;
                $this->log($report, $actor, 'assigned', $assignee ? "Ditugaskan kepada {$assignee->name}." : 'Penugasan dilepas.');
                if ($assignee && $assignee->id !== $actor->id) {
                    $this->notify->toUser($assignee, 'Tugas baru '.$report->report_number, "Anda ditugaskan menangani: \"{$report->title}\".", $report);
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

                if ($report->status === 'selesai') {
                    $report->resolved_at = $now;
                } elseif (in_array($report->status, Report::OPEN_STATUSES, true)) {
                    $report->resolved_at = null; // dibuka kembali
                }

                $note = trim((string) ($data['follow_up_note'] ?? ''));
                $this->log($report, $actor, 'status', "Status diubah dari {$old} menjadi {$report->statusLabel()}.", $note !== '' ? ['note' => $note] : null);
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
            }
        });

        return $report->refresh();
    }

    // ------------------------------------------------------------- pindah

    public function transfer(Report $report, User $actor, Team $to, Category $category, ?string $reason): Report
    {
        $from = $report->team;

        DB::transaction(function () use ($report, $actor, $to, $category, $reason, $from) {
            $report->team_id = $to->id;
            $report->category_id = $category->id;
            $report->custom_category = null;
            $report->assigned_to_id = null;
            $report->inventory_item_id = null; // item inventaris milik regu lama
            $report->status = 'menunggu';
            $report->resolved_at = null;
            $report->save();

            $this->log($report, $actor, 'transferred', "Laporan dipindahkan dari {$from->name} ke {$to->name}.".($reason ? " Alasan: {$reason}" : ''));
        });

        $report->load('team');
        $this->notify->toTeam($to, 'Laporan dialihkan '.$report->report_number, "Laporan \"{$report->title}\" dialihkan dari {$from->name}.", $report, [$actor->id]);

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
            $report->update(['status' => 'dibatalkan']);
            $this->log($report, $actor, 'cancelled', 'Laporan dibatalkan oleh pelapor.'.($reason ? " Alasan: {$reason}" : ''));
        });

        $this->notify->toTeam($report->team, 'Laporan dibatalkan '.$report->report_number, "{$actor->name} membatalkan laporan \"{$report->title}\".", $report, [$actor->id]);

        return $report;
    }

    // ------------------------------------------------- ambil / lepas tugas

    /** Petugas mengambil laporan untuk dikerjakan sendiri (status otomatis "diproses"). */
    public function claim(Report $report, User $actor): Report
    {
        DB::transaction(function () use ($report, $actor) {
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
        }

        return $report->refresh();
    }

    /** Melepas tugas: laporan kembali ke antrean regu agar bisa diambil/ditugaskan ulang. */
    public function release(Report $report, User $actor, ?string $reason = null): Report
    {
        DB::transaction(function () use ($report, $actor, $reason) {
            $previous = $report->assignee;
            $report->assigned_to_id = null;
            if ($report->status === 'diproses') {
                $report->status = 'menunggu';
            }
            $report->save();

            $who = $previous && $previous->id !== $actor->id ? "Penugasan {$previous->name} dilepas oleh {$actor->name}." : "{$actor->name} melepas tugas ini kembali ke antrean regu.";
            $this->log($report, $actor, 'released', $who.($reason ? " Alasan: {$reason}" : ''));
        });

        if ($actor->isStaf()) {
            $this->notify->toHead($report->team, 'Tugas dilepas '.$report->report_number, "{$actor->name} melepas \"{$report->title}\" ke antrean.", $report, [$actor->id]);
        }

        return $report->refresh();
    }

    // ------------------------------------------------------------- internal

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
