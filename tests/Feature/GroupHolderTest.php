<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Aset milik bidang / divisi di Inventaris: API struktur organisasi dan barang yang boleh dilaporkan. */
class GroupHolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_endpoint_lists_divisions_and_departments(): void
    {
        config(['services.inventaris.token' => 'token-rahasia-0123456789']);
        $div = Division::create(['name' => 'SDM, Umum & K3', 'sort_order' => 2]);
        Department::create(['name' => 'Umum & IT', 'division_id' => $div->id, 'sort_order' => 1]);
        Department::create(['name' => 'Tanpa Induk']);

        $this->getJson('/api/v1/organization')->assertStatus(401);
        $this->getJson('/api/v1/organization', ['Authorization' => 'Bearer token-rahasia-0123456789'])->assertOk()
            ->assertJsonPath('app', 'HaloDek')
            ->assertJsonFragment(['id' => $div->id, 'name' => 'SDM, Umum & K3', 'sort_order' => 2])
            ->assertJsonFragment(['name' => 'Umum & IT', 'division_id' => $div->id])
            ->assertJsonFragment(['name' => 'Tanpa Induk', 'division_id' => null]);
    }

    public function test_items_held_by_own_department_or_division_are_listed(): void
    {
        $this->seedTeams();
        $it = Team::where('slug', 'it')->first();
        $div = Division::create(['name' => 'Keuangan']);
        $dept = Department::create(['name' => 'Akuntansi', 'division_id' => $div->id]);
        $emp = $this->user(User::ROLE_KARYAWAN, null, ['nik' => '82001', 'division_id' => $div->id, 'department_id' => $dept->id, 'department' => 'Akuntansi']);

        $this->fakeInventaris([
            $this->fakeAsset(201, 'it', ['code' => 'PRN-1', 'name' => 'Printer Akuntansi', 'label' => 'PRN-1 - Printer Akuntansi', 'location' => null,
                'holder' => ['type' => 'bidang', 'nik' => null, 'name' => 'Bidang Akuntansi']]),
            $this->fakeAsset(202, 'it', ['code' => 'PRJ-1', 'name' => 'Proyektor', 'location' => null,
                'holder' => ['type' => 'divisi', 'nik' => null, 'name' => 'Divisi Keuangan']]),
            $this->fakeAsset(203, 'it', ['code' => 'PRN-2', 'name' => 'Printer HR', 'location' => null,
                'holder' => ['type' => 'bidang', 'nik' => null, 'name' => 'Bidang SDM']]),
        ]);

        $this->actingAs($emp)->getJson(route('employee.reports.items', ['team_id' => $it->id]))->assertOk()
            ->assertJsonFragment(['id' => 201, 'label' => 'PRN-1 - Printer Akuntansi · Bidang Akuntansi'])
            ->assertJsonFragment(['id' => 202])
            ->assertJsonMissing(['id' => 203]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'holder_department=Akuntansi') && str_contains($r->url(), 'holder_division=Keuangan'));
    }
}
