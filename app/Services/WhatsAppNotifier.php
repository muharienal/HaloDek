<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use App\Support\Phone;
use App\Support\WaRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi WhatsApp satu arah lewat Fonnte. Siapa menerima apa diatur per peran di
 * Administrasi > WhatsApp (WaRules) dan bisa dimatikan sendiri oleh tiap pengguna di Profil.
 * Nomor diambil dari kolom No. WhatsApp di profil.
 * Gagal kirim tidak pernah mengganggu proses laporan; pesan dikirim setelah respons selesai.
 */
class WhatsAppNotifier
{
    /**
     * Laporan baru (atau dialihkan) ke sebuah regu.
     * Laporan berprioritas penting yang tidak punya penerima sama sekali dikirim ke Manajer
     * (atau Superadmin) agar tidak terlewat.
     */
    public function newReport(Report $report, ?string $headline = null, array $exclude = []): void
    {
        $cfg = config('services.whatsapp');
        if (! $this->enabled($cfg)) {
            return;
        }

        try {
            $report->loadMissing(['team', 'user', 'category']);
            $important = in_array($report->priority, WaRules::importantPriorities(), true);
            $headline ??= $important ? 'LAPORAN '.mb_strtoupper($report->priorityLabel()).' MASUK' : 'LAPORAN BARU MASUK';

            $exclude = array_merge($exclude, [$report->user_id]);
            $users = $this->numbersOf($this->usersFor('baru', $report->team_id, $report->priority, $exclude));
            if (! $users && $important) {
                $users = $this->fallbackNumbers('baru', $exclude);
            }
            if ($users) {
                $this->queue($users, $this->headMessage($report, $headline));
            }
        } catch (Throwable $e) {
            Log::warning('Notifikasi WhatsApp laporan baru gagal disiapkan: '.$e->getMessage());
        }
    }

    /** Laporan dialihkan ke regu lain: penerima "Laporan baru" di regu tujuan diberi tahu. */
    public function transferred(Report $report, User $actor): void
    {
        $report->loadMissing('team');
        $this->newReport($report, 'LAPORAN DIALIHKAN KE REGU '.mb_strtoupper((string) ($report->team->name ?? '')), [$actor->id]);
    }

    /** @var array<string, array{actor: string, reports: array<int, Report>}> penugasan dalam request ini, per nomor */
    private array $assignments = [];

    /**
     * Penanggung jawab baru ditunjuk orang lain. Penugasan massal ke orang yang sama
     * digabung menjadi satu pesan (dikirim setelah respons selesai).
     */
    public function assigned(Report $report, User $assignee, User $actor): void
    {
        if (! $this->enabled(config('services.whatsapp')) || $assignee->id === $actor->id) {
            return;
        }

        try {
            $to = $assignee->wantsWa('tugas') ? $assignee->waNumber() : null;
            if (! $to) {
                return;
            }
            $first = $this->assignments === [];
            $this->assignments[$to]['actor'] = $actor->name;
            $this->assignments[$to]['reports'][$report->id] = $report;
            if ($first) {
                $cfg = config('services.whatsapp');
                $done = false;
                app()->terminating(function () use ($cfg, &$done) {
                    if ($done) {
                        return;
                    }
                    $done = true;
                    $batch = $this->assignments;
                    $this->assignments = [];
                    foreach ($batch as $number => $item) {
                        try {
                            $this->send($cfg, $number, $this->assignedMessage(array_values($item['reports']), $item['actor']));
                        } catch (Throwable $e) {
                            Log::warning('Notifikasi WhatsApp penugasan gagal: '.$e->getMessage());
                        }
                    }
                });
            }
        } catch (Throwable $e) {
            Log::warning('Notifikasi WhatsApp penugasan gagal disiapkan: '.$e->getMessage());
        }
    }

    /** @param array<int, Report> $reports */
    public function assignedMessage(array $reports, string $actorName): string
    {
        if (count($reports) === 1) {
            return $this->headMessage($reports[0], 'TUGAS BARU UNTUK ANDA', null, 'Ditugaskan oleh '.$actorName.'.');
        }

        $lines = ['*'.count($reports).' TUGAS BARU UNTUK ANDA*', '', 'Ditugaskan oleh '.$actorName.':'];
        foreach (array_slice($reports, 0, 10) as $i => $r) {
            $lines[] = '';
            $lines[] = ($i + 1).'. '.$r->report_number.' · '.$r->priorityLabel();
            $lines[] = '   '.\Illuminate\Support\Str::limit($r->title, 80);
            $lines[] = '   '.route('admin.reports.show', $r);
        }
        if (count($reports) > 10) {
            $lines[] = '';
            $lines[] = '…dan '.(count($reports) - 10).' tugas lain.';
        }

        return implode("\n", $lines);
    }

    /**
     * Banding atau "belum beres" dari pelapor. Penerima: aturan "Banding & dibuka lagi"
     * (Staf hanya bila ia penanggung jawabnya). Tanpa penerima -> Manajer/Superadmin.
     *
     * @return array<int, string> nomor tujuan
     */
    public function objection(Report $report, string $headline, ?string $note = null): array
    {
        $cfg = config('services.whatsapp');
        if (! $this->enabled($cfg)) {
            return [];
        }

        try {
            $users = $this->usersFor('keberatan', $report->team_id, null, [$report->user_id])
                ->reject(fn (User $u) => $u->role === User::ROLE_STAF && $u->id !== $report->assigned_to_id);
            $targets = $this->numbersOf($users);
            if (! $targets) {
                $targets = $this->fallbackNumbers('keberatan', [$report->user_id]);
            }
            if ($targets) {
                $this->queue($targets, $this->headMessage($report, $headline, $note));
            }

            return $targets;
        } catch (Throwable $e) {
            Log::warning('Notifikasi WhatsApp banding gagal disiapkan: '.$e->getMessage());

            return [];
        }
    }

    /** Balasan (bukan catatan internal) pada laporan: ke pelapor, atau ke petugas bila pelapor yang membalas. */
    public function reply(Report $report, User $actor, string $text): void
    {
        if (! $this->enabled(config('services.whatsapp'))) {
            return;
        }

        try {
            $report->loadMissing(['user', 'team', 'assignee']);
            $short = trim(preg_replace('/\s+/', ' ', $text));
            $short = mb_strlen($short) > 200 ? mb_substr($short, 0, 200).'…' : $short;

            if ($actor->isAdmin()) {
                $reporter = $report->user;
                if ($reporter && $reporter->id !== $actor->id && $reporter->wantsWa('balasan') && ($to = $reporter->waNumber())) {
                    $this->queue([$to], $this->replyMessage($report, $actor, $short, $report->publicUrl()));
                }

                return;
            }

            $users = $report->assigned_to_id
                ? collect([$report->assignee])->filter(fn ($u) => $u && $u->wantsWa('balasan'))
                : $this->usersFor('balasan', $report->team_id, null, [$actor->id])
                    ->filter(fn (User $u) => in_array($u->role, [User::ROLE_KEPALA_REGU, User::ROLE_STAF], true) && (int) $u->team_id === (int) $report->team_id);
            $targets = array_values(array_diff($this->numbersOf($users->reject(fn ($u) => $u->id === $actor->id)), [$actor->waNumber()]));
            if ($targets) {
                $this->queue($targets, $this->replyMessage($report, $actor, $short, route('admin.reports.show', $report)));
            }
        } catch (Throwable $e) {
            Log::warning('Notifikasi WhatsApp balasan gagal disiapkan: '.$e->getMessage());
        }
    }

    public function replyMessage(Report $report, User $actor, string $text, string $url): string
    {
        return implode("\n", [
            '*BALASAN BARU*',
            '',
            '*'.mb_strtoupper($report->title).'*',
            'No. laporan: '.$report->report_number,
            '',
            $actor->name.':',
            $text,
            '',
            'Buka laporan:',
            $url,
        ]);
    }

    public function headMessage(Report $report, string $headline, ?string $note = null, ?string $extraLine = null): string
    {
        $report->loadMissing(['team', 'user', 'category']);
        $desc = trim(preg_replace('/\s+/', ' ', (string) ($note ?? $report->description)));
        $desc = mb_strlen($desc) > 200 ? mb_substr($desc, 0, 200).'…' : $desc;

        $lines = [
            '*'.$headline.'*',
            '',
            '*'.mb_strtoupper($report->title).'*',
            'No. laporan: '.$report->report_number,
            'Regu: Regu '.($report->team->name ?? '-'),
            'Prioritas: '.$report->priorityLabel(),
        ];
        if ($report->location_name) {
            $lines[] = 'Lokasi: '.$report->location_name;
        }
        $lines[] = 'Pelapor: '.($report->reporter_name ?: ($report->user->name ?? '-'));
        if ($extraLine) {
            $lines[] = $extraLine;
        }
        $lines[] = '';
        $lines[] = ($note !== null ? 'Alasan pelapor:' : 'Keterangan:');
        $lines[] = $desc;
        $lines[] = '';
        $lines[] = 'Tangani di HaloDek:';
        $lines[] = route('admin.reports.show', $report);

        return implode("\n", $lines);
    }

    /** Kirim teks bebas ke nomor-nomor tertentu, langsung (tidak ditunda). Mengembalikan jumlah yang berhasil. */
    public function sendNow(array $numbers, string $message): int
    {
        $cfg = config('services.whatsapp');
        if (! $this->enabled($cfg)) {
            return 0;
        }

        return $this->deliver($cfg, $numbers, $message);
    }

    /**
     * Kirim satu pesan ke banyak nomor sekaligus: Fonnte menerima beberapa nomor dalam satu panggilan
     * (dipisah koma), jadi 10 penerima = 1 panggilan, bukan 10. Mengembalikan jumlah nomor yang berhasil.
     */
    private function deliver(array $cfg, array $numbers, string $message): int
    {
        $sent = 0;
        foreach (array_chunk(array_values(array_unique(array_filter($numbers))), 50) as $chunk) {
            try {
                $this->send($cfg, implode(',', $chunk), $message);
                $sent += count($chunk);
            } catch (Throwable $e) {
                Log::warning('WhatsApp ke '.implode(', ', $chunk).' gagal: '.$e->getMessage());
            }
        }

        return $sent;
    }

    /**
     * Pengguna aktif ber-No. WhatsApp yang mau menerima kabar ini.
     * Superadmin & Manajer: semua regu. Kepala Regu & Staf: hanya regunya ($teamId).
     *
     * @return Collection<int, User>
     */
    public function usersFor(string $event, ?int $teamId, ?string $priority = null, array $exclude = []): Collection
    {
        $roles = WaRules::rolesFor($event, $priority);
        if (! $roles) {
            return collect();
        }

        return User::active()->whereIn('role', $roles)->whereNotNull('phone')->where('phone', '!=', '')
            ->whereNotIn('id', array_filter($exclude))
            ->where(function ($q) use ($teamId) {
                $q->whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_MANAJER]);
                if ($teamId) {
                    $q->orWhere('team_id', $teamId);
                }
            })
            ->orderBy('id')->get()
            ->filter(fn (User $u) => $u->wantsWa($event, $priority) && $u->waNumber())
            ->values();
    }

    /** @return array<int, string> */
    public function numbersOf(Collection $users): array
    {
        return $users->map->waNumber()->filter()->unique()->values()->all();
    }

    /**
     * Cadangan agar kabar penting tidak hilang bila tak ada penerima: Manajer ber-No. WA,
     * atau Superadmin bila belum ada Manajer (kecuali yang mematikan kabar ini di profilnya).
     *
     * @return array<int, string>
     */
    public function fallbackNumbers(string $event, array $exclude = []): array
    {
        foreach ([User::ROLE_MANAJER, User::ROLE_SUPERADMIN] as $role) {
            $users = User::active()->where('role', $role)->whereNotNull('phone')->whereNotIn('id', array_filter($exclude))->orderBy('id')->get()
                ->reject(fn (User $u) => in_array($event, (array) ($u->wa_off ?? []), true));
            if ($numbers = $this->numbersOf($users)) {
                return $numbers;
            }
        }

        return [];
    }

    /** Kabar status ke pelapor. Hanya untuk status diproses/selesai/ditolak dan bila pelapor mengisi nomor WA. */
    public function reporterUpdate(Report $report): void
    {
        if (! $this->enabled(config('services.whatsapp')) || ! in_array($report->status, ['diproses', 'selesai', 'ditolak'], true)) {
            return;
        }

        try {
            $report->loadMissing(['user', 'team', 'assignee']);
            $to = $report->user?->wantsWa('laporan_saya') ? $report->user->waNumber() : null;
            if ($to) {
                $this->queue([$to], $this->reporterMessage($report));
            }
        } catch (Throwable $e) {
            Log::warning('Notifikasi WhatsApp ke pelapor gagal disiapkan: '.$e->getMessage());
        }
    }

    /**
     * Pengingat laporan lewat batas respons. Penerima: aturan "Lewat batas respons";
     * tanpa penerima -> Manajer/Superadmin. Dikirim langsung (dipanggil dari tugas latar).
     *
     * @param  \Illuminate\Support\Collection<int, Report>  $reports
     * @return int jumlah nomor yang berhasil dikirimi
     */
    public function overdueReminder(\App\Models\Team $team, \Illuminate\Support\Collection $reports): int
    {
        $cfg = config('services.whatsapp');
        if (! $this->enabled($cfg) || $reports->isEmpty()) {
            return 0;
        }

        $targets = $this->numbersOf($this->usersFor('lewat_batas', $team->id));
        if (! $targets) {
            $targets = $this->fallbackNumbers('lewat_batas');
        }

        return $this->sendNow($targets, $this->overdueMessage($team, $reports));
    }

    /** @param  \Illuminate\Support\Collection<int, Report>  $reports */
    public function overdueMessage(\App\Models\Team $team, \Illuminate\Support\Collection $reports): string
    {
        $lines = [
            '*PENGINGAT: LAPORAN BELUM DIAMBIL*',
            '',
            'Regu '.$team->name.', '.$reports->count().' laporan sudah melewati batas respons:',
        ];
        foreach ($reports->take(10)->values() as $i => $r) {
            $lines[] = '';
            $lines[] = ($i + 1).'. '.$r->report_number.' · '.$r->priorityLabel();
            $lines[] = '   '.\Illuminate\Support\Str::limit($r->title, 80).' (menunggu '.$this->hoursText($r->waitingWorkHours()).')';
            $lines[] = '   '.$r->publicUrl();
        }
        if ($reports->count() > 10) {
            $lines[] = '';
            $lines[] = '…dan '.($reports->count() - 10).' laporan lain.';
        }
        $lines[] = '';
        $lines[] = 'Mohon segera diambil atau ditugaskan di HaloDek.';

        return implode("\n", $lines);
    }

    private function hoursText(float $h): string
    {
        return ($h == (int) $h ? (int) $h : str_replace('.', ',', (string) $h)).' jam kerja';
    }

    public function reporterMessage(Report $report): string
    {
        $report->loadMissing(['user', 'team', 'assignee']);
        $first = \Illuminate\Support\Str::words($report->user->name ?? 'Karyawan', 1, '');
        $note = trim(preg_replace('/\s+/', ' ', (string) $report->follow_up_note));
        $note = mb_strlen($note) > 200 ? mb_substr($note, 0, 200).'…' : $note;

        [$head, $intro, $noteLabel] = match ($report->status) {
            'selesai' => ['LAPORAN ANDA SUDAH SELESAI', 'laporan Anda sudah selesai ditangani.', 'Catatan penyelesaian'],
            'ditolak' => $report->duplicate_of_id
                ? ['LAPORAN ANDA DIGABUNG', 'masalah yang sama sudah dilaporkan dan sedang ditangani.', 'Keterangan']
                : ['LAPORAN ANDA TIDAK DAPAT DIPROSES', 'mohon maaf, laporan Anda tidak dapat dilanjutkan.', 'Alasan'],
            default => ['LAPORAN ANDA SEDANG DIPROSES', 'laporan Anda sedang ditangani.', 'Catatan penanggung jawab'],
        };

        $lines = [
            '*'.$head.'*',
            '',
            'Halo '.$first.', '.$intro,
            '',
            '*'.mb_strtoupper($report->title).'*',
            'No. laporan: '.$report->report_number,
            'Regu: Regu '.($report->team->name ?? '-'),
        ];
        if ($report->status === 'diproses' && $report->assignee) {
            $lines[] = 'Penanggung jawab: '.$report->assignee->name;
        }
        if ($note !== '') {
            $lines[] = $noteLabel.': '.$note;
        }
        if ($report->status === 'selesai') {
            $lines[] = '';
            $lines[] = 'Belum beres? Buka laporan ini di HaloDek (menu Laporan Saya), lalu pilih *Belum beres* dalam '.config('halodek.reopen_days').' hari.';
        }
        $lines[] = '';
        $lines[] = 'Pantau status terkini:';
        $lines[] = ($report->duplicate_of_id && $report->duplicateOf) ? $report->duplicateOf->publicUrl() : $report->publicUrl();

        return implode("\n", $lines);
    }

    private function enabled(array $cfg): bool
    {
        $driver = $cfg['driver'] ?? 'off';

        // Fonnte tanpa token pasti gagal: anggap mati agar tidak memenuhi log dengan galat.
        return $driver === 'log' || ($driver === 'fonnte' && trim((string) ($cfg['token'] ?? '')) !== '');
    }

    /** Kirim setelah respons ke browser selesai; gagal kirim hanya dicatat di log. */
    private function queue(array $targets, string $message): void
    {
        $cfg = config('services.whatsapp');

        // Callback "terminating" bisa terpanggil lagi bila aplikasi melayani >1 request (tes/Octane):
        // tanda $done mencegah pesan yang sama terkirim ulang.
        $done = false;
        app()->terminating(function () use ($cfg, $targets, $message, &$done) {
            if ($done) {
                return;
            }
            $done = true;
            $this->deliver($cfg, $targets, $message);
        });
    }

    private function send(array $cfg, string $to, string $message): void
    {
        match ($cfg['driver']) {
            'log' => Log::info("[WA ke {$to}]\n{$message}"),
            // Batas tunggu pendek: pengiriman berjalan setelah halaman tampil, tetapi tetap memakai proses yang sama.
            'fonnte' => $this->check(Http::connectTimeout(3)->timeout($cfg['timeout'] ?? 5)->withHeaders(['Authorization' => $cfg['token']])
                ->asForm()->post('https://api.fonnte.com/send', ['target' => $to, 'message' => $message, 'countryCode' => '62'])),
            default => null,
        };
    }

    private function check($response): void
    {
        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }
        // Fonnte membalas HTTP 200 dengan status false bila ditolak (token salah, perangkat terputus, kuota habis).
        $json = $response->json();
        if (is_array($json) && ($json['status'] ?? null) === false) {
            throw new \RuntimeException('Ditolak Fonnte: '.mb_substr((string) ($json['reason'] ?? $json['detail'] ?? 'tanpa alasan'), 0, 200));
        }
    }
}
