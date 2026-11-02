<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
        Storage::fake('local');
    }

    public function test_security_headers_and_csp_nonce_are_sent(): void
    {
        $res = $this->get('/login')->assertOk();
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9_-]+'/", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertSame('DENY', $res->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));

        // Nonce pada header sama dengan nonce pada tag <script> inline.
        preg_match("/nonce-([A-Za-z0-9_-]+)/", $csp, $m);
        $this->assertStringContainsString('nonce="'.$m[1].'"', $res->getContent());
    }

    public function test_new_admin_gets_random_temporary_password_not_nik(): void
    {
        $su = $this->user(User::ROLE_SUPERADMIN);
        $team = Team::where('slug', 'it')->first();

        $res = $this->actingAs($su)->post(route('admin.users.store'), ['nik' => '80001', 'name' => 'Staf Baru', 'role' => 'staf', 'team_id' => $team->id]);
        $res->assertSessionHas('tempPassword');

        $temp = session('tempPassword')['password'];
        $user = User::where('nik', '80001')->first();
        $this->assertNotSame('80001', $temp);
        $this->assertGreaterThanOrEqual(12, strlen($temp));
        $this->assertTrue(Hash::check($temp, $user->password));
        $this->assertFalse(Hash::check('80001', $user->password));
        $this->assertTrue($user->must_change_password);
    }

    public function test_karyawan_gets_no_guessable_password(): void
    {
        $su = $this->user(User::ROLE_SUPERADMIN);
        $this->actingAs($su)->post(route('admin.users.store'), ['nik' => '80002', 'name' => 'Karyawan', 'role' => 'karyawan'])->assertSessionMissing('tempPassword');

        $this->assertFalse(Hash::check('80002', User::where('nik', '80002')->first()->password));
    }

    public function test_reset_password_gives_random_value_and_rejects_karyawan(): void
    {
        $su = $this->user(User::ROLE_SUPERADMIN);
        $staf = $this->user(User::ROLE_STAF, 'it', ['password' => 'LamaAman123']);
        $emp = $this->user();

        $this->actingAs($su)->post(route('admin.users.reset-password', $staf))->assertSessionHas('tempPassword');
        $this->assertFalse(Hash::check('LamaAman123', $staf->fresh()->password));
        $this->assertFalse(Hash::check($staf->nik, $staf->fresh()->password));

        $this->actingAs($su)->post(route('admin.users.reset-password', $emp))->assertSessionHas('error');
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $staf = $this->user(User::ROLE_STAF, 'it', ['password' => 'LamaAman123']);

        foreach (['pendek1A', 'semuahurufkecil1', 'SEMUAHURUFBESAR1'] as $weak) {
            $this->actingAs($staf)->put(route('profile.password'), [
                'current_password' => 'LamaAman123', 'password' => $weak, 'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }
    }

    public function test_attachments_require_access_and_are_served_safely(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $team = Team::where('slug', 'it')->first();

        $this->actingAs($owner)->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->value('id'),
            'priority' => 'sedang', 'title' => 'Uji', 'description' => 'Uji lampiran',
            'photos' => [UploadedFile::fake()->image('a.png')],
        ])->assertRedirect();

        $att = ReportAttachment::firstOrFail();

        $this->actingAs($other)->get(route('attachments.show', $att))->assertForbidden();
        $this->actingAs($owner)->get(route('attachments.show', $att))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // Tipe tak dikenal tidak pernah ditampilkan inline.
        $att->update(['mime' => 'text/html']);
        $res = $this->actingAs($owner)->get(route('attachments.show', $att))->assertOk();
        $this->assertSame('application/octet-stream', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_dangerous_upload_types_are_rejected(): void
    {
        $owner = $this->user();
        $team = Team::where('slug', 'it')->first();
        $payload = fn ($file) => [
            'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->value('id'),
            'priority' => 'sedang', 'title' => 'Uji', 'description' => 'Uji', 'photos' => [$file],
        ];

        foreach ([
            UploadedFile::fake()->createWithContent('x.php', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('x.html', '<script>alert(1)</script>'),
        ] as $bad) {
            $this->actingAs($owner)->post(route('employee.reports.store'), $payload($bad))->assertSessionHasErrors('photos.0');
        }
        $this->assertSame(0, Report::count());
    }

    public function test_employee_cannot_reach_other_employees_reports_or_admin_actions(): void
    {
        $a = $this->user();
        $b = $this->user();
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($a)->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->value('id'),
            'priority' => 'sedang', 'title' => 'Milik A', 'description' => 'x',
        ]);
        $r = Report::firstOrFail();

        $this->actingAs($b)->get(route('employee.reports.show', $r))->assertForbidden();
        $this->actingAs($b)->post(route('employee.reports.comment', $r), ['message' => 'halo'])->assertForbidden();
        $this->actingAs($b)->patch(route('employee.reports.cancel', $r))->assertForbidden();
        $this->actingAs($b)->post(route('admin.reports.take', $r))->assertForbidden();
        $this->actingAs($b)->patch(route('admin.reports.update', $r), ['status' => 'selesai', 'follow_up_note' => 'x'])->assertForbidden();
        $this->actingAs($b)->delete(route('admin.reports.destroy', $r))->assertForbidden();
        $this->actingAs($b)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($b)->post(route('admin.users.store'), ['nik' => '1', 'name' => 'x', 'role' => 'superadmin'])->assertForbidden();
    }

    public function test_staf_and_head_cannot_use_superadmin_features(): void
    {
        $head = $this->user(User::ROLE_KEPALA_REGU, 'it');
        $staf = $this->user(User::ROLE_STAF, 'it');
        $victim = $this->user();

        foreach ([$head, $staf] as $u) {
            $this->actingAs($u)->get(route('admin.users.index'))->assertForbidden();
            $this->actingAs($u)->get(route('admin.audit.index'))->assertForbidden();
            $this->actingAs($u)->patch(route('admin.users.update', $victim), ['nik' => $victim->nik, 'name' => 'x', 'role' => 'superadmin'])->assertForbidden();
            $this->actingAs($u)->post(route('admin.users.reset-password', $victim))->assertForbidden();
            $this->actingAs($u)->post(route('admin.teams.store'), ['name' => 'X'])->assertForbidden();
        }
        $this->assertSame('karyawan', $victim->fresh()->role);
    }

    public function test_deactivated_user_is_kicked_out_immediately(): void
    {
        $staf = $this->user(User::ROLE_STAF, 'it');
        $this->actingAs($staf)->get('/dashboard')->assertOk();

        $staf->update(['is_active' => false]);
        $this->actingAs($staf->fresh())->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_profile_update_cannot_change_role(): void
    {
        $emp = $this->user();
        $this->actingAs($emp)->put(route('profile.update'), ['email' => 'a@b.co', 'role' => 'superadmin', 'is_active' => 0, 'team_id' => 1])->assertRedirect();

        $this->assertSame('karyawan', $emp->fresh()->role);
        $this->assertNull($emp->fresh()->team_id);
    }

    public function test_csv_formula_injection_is_neutralised(): void
    {
        $this->assertSame("'=1+1", \App\Support\Csv::safe('=1+1'));
        $this->assertSame("'@SUM(A1)", \App\Support\Csv::safe('@SUM(A1)'));
        $this->assertSame('aman', \App\Support\Csv::safe('aman'));
    }

    public function test_output_is_escaped(): void
    {
        $emp = $this->user();
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($emp)->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->value('id'),
            'priority' => 'sedang', 'title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>',
        ]);
        $r = Report::firstOrFail();

        $html = $this->actingAs($emp)->get(route('employee.reports.show', $r))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
    }
}
