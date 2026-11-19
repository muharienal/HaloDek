<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Manajer = supervisi: melihat laporan semua regu, tidak menangani laporan. */
class ManajerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manajer_monitors_all_teams_but_cannot_act(): void
    {
        $this->seedTeams();
        $emp = $this->user();
        $mgr = $this->user(User::ROLE_MANAJER, null, ['password' => 'rahasia123']);
        $ids = [];
        foreach (['it', 'keamanan'] as $slug) {
            $team = Team::where('slug', $slug)->first();
            $this->actingAs($emp)->post(route('employee.reports.store'), [
                'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->where('allow_custom', false)->value('id'),
                'priority' => 'sedang', 'title' => 'Uji '.$slug, 'description' => 'x',
            ])->assertRedirect();
            $ids[] = Report::latest('id')->value('id');
        }

        // Masuk dengan NIK + password (sama seperti pengelola lain).
        auth()->logout();
        $this->post('/login', ['nik' => $mgr->nik, 'mode' => 'admin', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));

        $this->actingAs($mgr)->get(route('dashboard'))->assertOk();
        $this->actingAs($mgr)->get(route('admin.reports.index'))->assertOk()->assertSee('Uji it')->assertSee('Uji keamanan')->assertDontSee('data-bulk-all', false);
        $this->actingAs($mgr)->get(route('admin.reports.show', $ids[0]))->assertOk()
            ->assertDontSee('Ambil Laporan')->assertDontSee(route('admin.reports.comment', $ids[0]), false);
        $this->actingAs($mgr)->get(route('admin.monthly.index'))->assertOk();
        $this->actingAs($mgr)->get(route('admin.reports.export'))->assertOk();
        $this->actingAs($mgr)->get(route('announcements.index'))->assertOk()->assertDontSee('Buat Pengumuman');

        foreach ([
            ['post', route('admin.reports.take', $ids[0])],
            ['post', route('admin.reports.close', $ids[0])],
            ['post', route('admin.reports.comment', $ids[0])],
            ['post', route('admin.reports.bulk')],
            ['patch', route('admin.reports.update', $ids[0])],
            ['post', route('admin.announcements.store')],
        ] as [$m, $url]) {
            $this->actingAs($mgr)->{$m}($url, ['message' => 'x', 'status' => 'selesai'])->assertForbidden();
        }
        $this->actingAs($mgr)->get(route('admin.users.index'))->assertForbidden();
        $this->assertSame(0, Report::whereNotNull('assigned_to_id')->count());
    }
}
