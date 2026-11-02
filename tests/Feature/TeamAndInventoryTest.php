<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamAndInventoryTest extends TestCase
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

    public function test_head_manages_inventory_categories_and_items_of_own_team_only(): void
    {
        $head = $this->user(User::ROLE_KEPALA_REGU, 'sarpras');
        $team = $head->team;
        $other = Team::where('slug', 'it')->first();

        $this->actingAs($head)->post(route('admin.inventory.categories.store'), ['team_id' => $team->id, 'name' => 'Genset'])->assertRedirect();
        $cat = InventoryCategory::where('team_id', $team->id)->where('name', 'Genset')->firstOrFail();

        $this->actingAs($head)->post(route('admin.inventory.items.store'), [
            'team_id' => $team->id, 'inventory_category_id' => $cat->id, 'code' => 'GN-1', 'name' => 'Genset 100kVA', 'status' => 'tersedia',
        ])->assertRedirect();
        $item = InventoryItem::where('code', 'GN-1')->firstOrFail();

        $this->actingAs($head)->patch(route('admin.inventory.items.update', $item), [
            'inventory_category_id' => $cat->id, 'code' => 'GN-1', 'name' => 'Genset 120kVA', 'status' => 'perbaikan',
        ])->assertRedirect();
        $this->assertSame('perbaikan', $item->fresh()->status);

        $this->actingAs($head)->post(route('admin.inventory.categories.store'), ['team_id' => $other->id, 'name' => 'X'])->assertForbidden();
        $this->actingAs($head)->get(route('admin.inventory.index', ['team_id' => $other->id]))->assertOk()->assertSee('Genset');

        $this->actingAs($head)->delete(route('admin.inventory.categories.destroy', $cat))->assertSessionHas('error');
        $this->actingAs($head)->delete(route('admin.inventory.items.destroy', $item))->assertRedirect();
        $this->actingAs($head)->delete(route('admin.inventory.categories.destroy', $cat))->assertRedirect();
        $this->assertDatabaseMissing('inventory_categories', ['id' => $cat->id]);
    }

    public function test_staf_can_edit_items_but_not_categories_or_delete(): void
    {
        $staf = $this->user(User::ROLE_STAF, 'it');
        $cat = InventoryCategory::where('team_id', $staf->team_id)->firstOrFail();

        $this->actingAs($staf)->post(route('admin.inventory.categories.store'), ['team_id' => $staf->team_id, 'name' => 'Baru'])->assertForbidden();
        $this->actingAs($staf)->post(route('admin.inventory.items.store'), [
            'team_id' => $staf->team_id, 'inventory_category_id' => $cat->id, 'code' => 'ST-1', 'name' => 'Switch', 'status' => 'tersedia',
        ])->assertRedirect();
        $item = InventoryItem::where('code', 'ST-1')->firstOrFail();
        $this->actingAs($staf)->delete(route('admin.inventory.items.destroy', $item))->assertForbidden();
        $this->actingAs($staf)->get(route('admin.inventory.items.show', $item))->assertOk();
    }

    public function test_staf_cannot_use_other_teams_category(): void
    {
        $staf = $this->user(User::ROLE_STAF, 'it');
        $foreign = InventoryCategory::where('team_id', '!=', $staf->team_id)->firstOrFail();

        $this->actingAs($staf)->post(route('admin.inventory.items.store'), [
            'team_id' => $staf->team_id, 'inventory_category_id' => $foreign->id, 'code' => 'ZZ', 'name' => 'x', 'status' => 'tersedia',
        ])->assertSessionHasErrors('inventory_category_id');
    }

    public function test_employee_can_link_report_to_item_through_json_endpoint(): void
    {
        $emp = $this->user();
        $team = Team::where('slug', 'it')->first();
        $cat = InventoryCategory::where('team_id', $team->id)->firstOrFail();
        $item = InventoryItem::create(['team_id' => $team->id, 'inventory_category_id' => $cat->id, 'code' => 'LP-1', 'name' => 'Laptop', 'status' => 'digunakan', 'holder_id' => $emp->id]);

        $this->actingAs($emp)->getJson(route('employee.reports.items', ['team_id' => $team->id]))->assertOk()->assertJsonFragment(['id' => $item->id]);
    }
}
