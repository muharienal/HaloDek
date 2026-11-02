<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

/**
 * Hak akses laporan:
 *  - Pelapor           : melihat, membalas, membatalkan laporannya sendiri.
 *  - Superadmin        : semua laporan.
 *  - Kepala Regu       : semua laporan regunya (menugaskan, mengambil, mengalihkan, menyelesaikan).
 *  - Staf              : melihat semua laporan regunya; hanya menindaklanjuti laporan yang ditugaskan
 *                        kepadanya, atau mengambil laporan yang belum ditugaskan.
 */
class ReportPolicy
{
    /** Pelapor, anggota regu terkait, atau Superadmin. */
    public function view(User $user, Report $report): bool
    {
        return $this->owns($user, $report) || $this->handles($user, $report);
    }

    /** Mengubah status / catatan / lampiran penyelesaian. */
    public function manage(User $user, Report $report): bool
    {
        if (! $this->handles($user, $report)) {
            return false;
        }

        return $user->leadsTeam($report->team_id) || ($user->isStaf() && $report->assigned_to_id === $user->id);
    }

    /** Menugaskan petugas, mengubah prioritas, mengalihkan regu. */
    public function assign(User $user, Report $report): bool
    {
        return $user->leadsTeam($report->team_id);
    }

    /** Mengambil laporan untuk dikerjakan sendiri. */
    public function claim(User $user, Report $report): bool
    {
        if (! $this->handles($user, $report) || ! $report->isOpen() || $report->assigned_to_id === $user->id) {
            return false;
        }

        // Kepala Regu boleh mengambil kapan saja; Staf hanya yang belum ditugaskan.
        return $user->leadsTeam($report->team_id) || ($user->isStaf() && $report->assigned_to_id === null);
    }

    /** Melepas tugas kembali ke antrean regu. */
    public function release(User $user, Report $report): bool
    {
        if (! $report->isOpen() || $report->assigned_to_id === null || ! $this->handles($user, $report)) {
            return false;
        }

        return $report->assigned_to_id === $user->id || $user->leadsTeam($report->team_id);
    }

    public function comment(User $user, Report $report): bool
    {
        if ($this->handles($user, $report)) {
            return true;
        }

        return $this->owns($user, $report) && $report->status !== 'dibatalkan';
    }

    public function cancel(User $user, Report $report): bool
    {
        return $this->owns($user, $report) && $report->status === 'menunggu';
    }

    public function delete(User $user, Report $report): bool
    {
        return $user->isSuperadmin();
    }

    private function owns(User $user, Report $report): bool
    {
        return $report->user_id !== null && $report->user_id === $user->id;
    }

    private function handles(User $user, Report $report): bool
    {
        return $user->isAdmin() && $user->handlesTeam($report->team_id);
    }
}
