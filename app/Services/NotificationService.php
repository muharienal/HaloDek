<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    public function toUser(int|User|null $user, string $title, string $message, ?Report $report = null): void
    {
        $id = $user instanceof User ? $user->id : $user;
        if (! $id) {
            return;
        }

        UserNotification::create([
            'user_id' => $id,
            'report_id' => $report?->id,
            'title' => $title,
            'message' => $message,
        ]);
    }

    /** Beritahu Kepala Regu (cadangan: Superadmin bila regu belum punya kepala aktif). */
    public function toHead(Team $team, string $title, string $message, ?Report $report = null, array $exceptUserIds = []): void
    {
        $ids = User::active()->where('team_id', $team->id)->where('role', User::ROLE_KEPALA_REGU)->pluck('id');

        if ($ids->isEmpty()) {
            $ids = User::active()->where('role', User::ROLE_SUPERADMIN)->pluck('id');
        }

        foreach ($ids->diff($exceptUserIds) as $id) {
            $this->toUser($id, $title, $message, $report);
        }
    }

    /**
     * Beritahu seluruh anggota aktif sebuah regu (Kepala Regu + Staf). Bila regu belum
     * punya anggota aktif, cadangan: semua Superadmin supaya tiket tidak "tenggelam".
     */
    public function toTeam(Team $team, string $title, string $message, ?Report $report = null, array $exceptUserIds = []): void
    {
        $ids = $team->users()->where('is_active', true)->pluck('id');

        if ($ids->isEmpty()) {
            $ids = User::active()->where('role', User::ROLE_SUPERADMIN)->pluck('id');
        }

        foreach ($ids->diff($exceptUserIds) as $id) {
            $this->toUser($id, $title, $message, $report);
        }
    }
}
