<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /** Pastikan admin yang login menangani regu tersebut (Superadmin selalu lolos). */
    protected function authorizeTeam(?int $teamId): void
    {
        abort_unless($teamId && auth()->user()->handlesTeam($teamId), 403, 'Anda tidak menangani regu ini.');
    }

    /** Regu yang boleh dikelola admin yang sedang login. */
    protected function handledTeams(): Collection
    {
        return Team::whereIn('id', auth()->user()->handledTeamIds() ?: [0])->ordered()->get();
    }
}
