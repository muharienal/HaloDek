<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\InventoryClient;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Kegagalan pelengkap (notifikasi, data Inventaris aneh) tidak boleh berujung error 500. */
class RobustnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
        Storage::fake('local');
    }

    private function post_report(User $emp, array $extra = [])
    {
        $team = Team::where('slug', 'it')->first();

        return $this->actingAs($emp)->post(route('employee.reports.store'), $extra + [
            'team_id' => $team->id,
            'category_id' => Category::where('team_id', $team->id)->where('allow_custom', false)->value('id'),
            'priority' => 'sedang', 'description' => 'Layar mati',
        ]);
    }

    public function test_report_is_saved_even_when_notifications_fail(): void
    {
        $this->user(User::ROLE_KEPALA_REGU, 'it');
        $emp = $this->user();
        $this->app->instance(NotificationService::class, new class extends NotificationService {
            public function toTeam(Team $team, string $title, string $message, ?Report $report = null, array $exceptUserIds = []): void
            {
                throw new \RuntimeException('DB notifikasi bermasalah');
            }
        });

        $r = $this->post_report($emp);
        $report = Report::latest('id')->first();
        $this->assertNotNull($report);
        $r->assertRedirect(route('employee.reports.show', $report))->assertSessionHas('success');
    }

    public function test_malformed_inventaris_data_is_cleaned(): void
    {
        config(['services.inventaris.url' => 'http://inv.test', 'services.inventaris.token' => 'token-rahasia-0123456789']);
        Http::fake([
            'inv.test/api/v1/locations*' => Http::response(['data' => [
                'teks', ['id' => 'x'], ['id' => 3, 'name' => 'Ruang A', 'label' => str_repeat('L', 300)], ['id' => 4, 'name' => ['bukan teks']],
            ]]),
            'inv.test/api/v1/assets/7*' => Http::response(['data' => ['id' => 7, 'code' => null, 'name' => str_repeat('N', 260), 'location' => 'rusak']]),
            'inv.test/api/v1/assets*' => Http::response(['data' => ['x', ['id' => 0], ['id' => 9, 'code' => 'AST-9', 'name' => 'Laptop']]]),
        ]);

        $inv = app(InventoryClient::class);
        $locs = $inv->locations();
        $this->assertCount(1, $locs);
        $this->assertSame(200, mb_strlen($locs[0]['label']));

        $assets = $inv->assets('it', '1', null);
        $this->assertSame([9], array_column($assets, 'id'));
        $this->assertSame('AST-9 – Laptop', $assets[0]['label']);

        $one = $inv->asset(7);
        $this->assertSame('#7', $one['code']);
        $this->assertSame(200, mb_strlen($one['name']));
        $this->assertNull($one['location']);
    }

    public function test_long_location_label_does_not_break_report(): void
    {
        config(['services.inventaris.url' => 'http://inv.test', 'services.inventaris.token' => 'token-rahasia-0123456789']);
        Http::fake(['inv.test/api/v1/locations*' => Http::response(['data' => [['id' => 5, 'name' => 'Ruang', 'label' => str_repeat('Gedung ', 60)]]])]);

        $this->post_report($this->user(), ['location_id' => 5])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(5, Report::latest('id')->first()->inventory_location_id);
        $this->assertLessThanOrEqual(200, mb_strlen(Report::latest('id')->first()->location_name));
    }

    public function test_api_requests_skip_background_tasks(): void
    {
        config(['services.inventaris.token' => 'token-rahasia-0123456789']);
        $this->withToken('token-rahasia-0123456789')->getJson('/api/v1/employees')->assertOk();
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has('halodek:pengingat:terakhir'));
    }
}
