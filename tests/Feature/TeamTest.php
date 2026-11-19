<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
    }

    public function test_four_default_teams_exist(): void
    {
        $this->assertEqualsCanonicalizing(['it', 'sarpras', 'kesekretariatan', 'keamanan'], Team::pluck('slug')->all());
    }

    public function test_superadmin_sets_new_head_and_old_head_becomes_staf(): void
    {
        $su = $this->user(User::ROLE_SUPERADMIN);
        $team = Team::where('slug', 'it')->first();
        $old = $this->user(User::ROLE_KEPALA_REGU, 'it');
        $new = $this->user(User::ROLE_STAF, 'it');

        $this->actingAs($su)->patch(route('admin.teams.members.head', [$team, $new]))->assertRedirect();

        $this->assertSame('kepala_regu', $new->fresh()->role);
        $this->assertSame('staf', $old->fresh()->role);
    }

    public function test_second_head_cannot_be_added_through_user_form(): void
    {
        $su = $this->user(User::ROLE_SUPERADMIN);
        $team = Team::where('slug', 'keamanan')->first();
        $this->user(User::ROLE_KEPALA_REGU, 'keamanan');

        $this->actingAs($su)->post(route('admin.users.store'), [
            'nik' => '70001', 'name' => 'Calon Kepala', 'role' => 'kepala_regu', 'team_id' => $team->id,
        ])->assertSessionHasErrors('team_id');
    }

    public function test_staf_needs_a_team(): void
    {
        $su = $this->user(User::ROLE_SUPERADMIN);
        $this->actingAs($su)->post(route('admin.users.store'), ['nik' => '70002', 'name' => 'Staf', 'role' => 'staf'])->assertSessionHasErrors('team_id');
    }
}
