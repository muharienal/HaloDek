<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Form laporan tanpa judul: judul otomatis, pencarian tetap menemukan isi laporan. */
class NoTitleFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_has_no_title_and_report_is_found_by_description(): void
    {
        $this->seedTeams();
        $emp = $this->user();
        $this->actingAs($emp)->get(route('employee.reports.create'))->assertOk()
            ->assertDontSee('name="title"', false)->assertSee('name="description"', false);

        $team = Team::where('slug', 'it')->first();
        $cat = Category::where('team_id', $team->id)->first();
        $this->actingAs($emp)->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => $cat->id, 'priority' => 'sedang', 'description' => 'Monitor berkedip terus sejak pagi',
        ])->assertRedirect();

        $r = Report::sole();
        $this->assertSame($cat->name, $r->title);
        $this->actingAs($emp)->get(route('employee.reports.index', ['q' => 'berkedip']))->assertSee($r->report_number);
    }

    public function test_no_long_dashes_in_views_and_assets(): void
    {
        $paths = array_merge(glob(resource_path('views/**/*.blade.php')) ?: [], glob(resource_path('views/*/*/*.blade.php')) ?: [],
            glob(public_path('css/*.css')) ?: [], glob(public_path('js/*.js')) ?: [], glob(app_path('*/*.php')) ?: [], glob(app_path('*/*/*.php')) ?: [], glob(app_path('*/*/*/*.php')) ?: []);
        $this->assertNotEmpty($paths);
        foreach ($paths as $p) {
            $this->assertDoesNotMatchRegularExpression('/[\x{2013}\x{2014}]/u', file_get_contents($p), $p);
        }
    }

    public function test_typed_long_dashes_are_saved_as_normal_dash(): void
    {
        $this->seedTeams();
        $team = Team::where('slug', 'it')->first();
        $this->actingAs($this->user())->post(route('employee.reports.store'), [
            'team_id' => $team->id, 'category_id' => Category::where('team_id', $team->id)->value('id'), 'priority' => 'sedang',
            'description' => "Printer lantai 2 \u{2014} macet \u{2013} kertas",
        ])->assertRedirect();
        $this->assertSame('Printer lantai 2 - macet - kertas', Report::sole()->description);
    }
}
