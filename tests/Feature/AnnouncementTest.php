<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_admin_can_post_and_everyone_sees_it(): void
    {
        $this->seedTeams();
        $staf = $this->user(User::ROLE_STAF, 'it');

        $this->actingAs($staf)->post(route('admin.announcements.store'), ['title' => 'Info', 'body' => 'Untuk semua', 'team_id' => 1])->assertSessionHasNoErrors();
        $this->assertNull(Announcement::firstOrFail()->team_id);

        foreach ([$this->user(), $this->user(User::ROLE_KEPALA_REGU, 'keamanan'), $this->user(User::ROLE_STAF, 'it')] as $u) {
            $this->actingAs($u)->get(route('announcements.index'))->assertOk()->assertSee('Untuk semua');
        }
    }

    public function test_karyawan_cannot_post_or_delete(): void
    {
        $this->seedTeams();
        $a = Announcement::create(['user_id' => $this->user(User::ROLE_STAF, 'it')->id, 'title' => 'x', 'body' => 'y']);
        $emp = $this->user();

        $this->actingAs($emp)->post(route('admin.announcements.store'), ['title' => 'a', 'body' => 'b'])->assertForbidden();
        $this->actingAs($emp)->delete(route('admin.announcements.destroy', $a))->assertForbidden();
    }
}
