<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Struktur organisasi: Divisi -> Bidang -> Regu, Jabatan, Peran; dipakai sebagai pilihan data pengguna. */
class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTeams();
        $this->super = $this->user(User::ROLE_SUPERADMIN);
    }

    public function test_only_superadmin_manages_structure(): void
    {
        $this->actingAs($this->user(User::ROLE_MANAJER))->get(route('admin.org.index'))->assertForbidden();
        $this->actingAs($this->user(User::ROLE_KEPALA_REGU, 'it'))->post(route('admin.org.store', 'divisi'), ['name' => 'X'])->assertForbidden();

        $this->actingAs($this->super)->get(route('admin.org.index'))->assertOk()->assertSee('Belum ada divisi');
        foreach (['bidang', 'jabatan', 'peran'] as $tab) {
            $this->actingAs($this->super)->get(route('admin.org.index', ['tab' => $tab]))->assertOk();
        }
    }

    public function test_crud_with_hierarchy_and_safe_delete(): void
    {
        $this->actingAs($this->super)->post(route('admin.org.store', 'divisi'), ['name' => 'SDM, Umum & K3'])->assertSessionHas('success');
        $div = Division::sole();
        $this->actingAs($this->super)->post(route('admin.org.store', 'bidang'), ['name' => 'Umum & IT'])->assertSessionHasErrors('division_id');
        $this->actingAs($this->super)->post(route('admin.org.store', 'bidang'), ['name' => 'Umum & IT', 'division_id' => $div->id])->assertSessionHas('success');
        $this->actingAs($this->super)->post(route('admin.org.store', 'bidang'), ['name' => 'umum & it', 'division_id' => $div->id])->assertSessionHasErrors('name');
        $this->actingAs($this->super)->post(route('admin.org.store', 'jabatan'), ['name' => 'Staf IT'])->assertSessionHas('success');
        $bidang = Department::sole();
        $jab = Position::sole();

        $u = $this->user(User::ROLE_KARYAWAN);
        $u->update(['department_id' => $bidang->id, 'position_id' => $jab->id]);
        $u->refresh();
        $this->assertSame([$div->id, 'Umum & IT', 'Staf IT'], [$u->division_id, $u->department, $u->position]);

        // Ganti nama -> salinan nama pengguna ikut berubah.
        $this->actingAs($this->super)->put(route('admin.org.update', ['bidang', $bidang->id]), ['name' => 'Umum dan IT', 'division_id' => $div->id])->assertSessionHas('success');
        $this->assertSame('Umum dan IT', $u->fresh()->department);

        // Masih dipakai -> tidak bisa dihapus.
        $this->actingAs($this->super)->delete(route('admin.org.destroy', ['divisi', $div->id]))->assertSessionHas('error');
        $this->actingAs($this->super)->delete(route('admin.org.destroy', ['jabatan', $jab->id]))->assertSessionHas('error');
        $this->assertNotNull($div->fresh());

        $u->update(['position_id' => null]);
        $this->actingAs($this->super)->delete(route('admin.org.destroy', ['jabatan', $jab->id]))->assertSessionHas('success');
        $this->assertNull($u->fresh()->position);
    }

    public function test_user_form_uses_dropdowns_and_keeps_hierarchy_consistent(): void
    {
        $div = Division::create(['name' => 'SDM, Umum & K3']);
        $other = Division::create(['name' => 'Keuangan']);
        $bidang = Department::create(['name' => 'Umum & IT', 'division_id' => $div->id]);
        $jab = Position::create(['name' => 'Staf IT']);
        $it = Team::where('slug', 'it')->first();
        $it->update(['department_id' => $bidang->id]);

        $this->actingAs($this->super)->get(route('admin.users.index'))->assertOk()
            ->assertSee('name="division_id"', false)->assertSee('name="department_id"', false)->assertSee('name="position_id"', false)
            ->assertDontSee('name="department" ', false);

        // Pilih regu saja -> bidang & divisi ikut terisi.
        $this->actingAs($this->super)->post(route('admin.users.store'), ['nik' => '7001', 'name' => 'Rafi', 'role' => 'staf', 'team_id' => $it->id, 'position_id' => $jab->id])
            ->assertSessionHas('success');
        $u = User::where('nik', '7001')->first();
        $this->assertSame([$div->id, $bidang->id, 'Umum & IT', 'Staf IT'], [$u->division_id, $u->department_id, $u->department, $u->position]);

        // Divisi bertentangan dengan bidang -> ditolak.
        $this->actingAs($this->super)->post(route('admin.users.store'), ['nik' => '7002', 'name' => 'Sinta', 'role' => 'karyawan', 'division_id' => $other->id, 'department_id' => $bidang->id])
            ->assertSessionHasErrors('department_id');
        $this->assertNull(User::where('nik', '7002')->first());
    }

    public function test_team_bidang_moves_members_and_role_labels_are_editable(): void
    {
        $div = Division::create(['name' => 'SDM, Umum & K3']);
        $bidang = Department::create(['name' => 'Umum & IT', 'division_id' => $div->id]);
        $staf = $this->user(User::ROLE_STAF, 'it');
        $it = Team::where('slug', 'it')->first();

        $this->actingAs($this->super)->patch(route('admin.teams.update', $it), [
            'name' => $it->name, 'code' => $it->code, 'department_id' => $bidang->id, 'icon' => $it->icon, 'color' => $it->color, 'is_active' => 1,
        ])->assertSessionHas('success');
        $this->assertSame($bidang->id, $staf->fresh()->department_id);
        $this->assertSame($div->id, $staf->fresh()->division_id);

        $this->actingAs($this->super)->put(route('admin.org.role', 'staf'), ['label' => 'Kepala Regu'])->assertSessionHas('error');
        $this->actingAs($this->super)->put(route('admin.org.role', 'staf'), ['label' => 'Petugas Regu', 'description' => 'Mengerjakan laporan regu.'])->assertSessionHas('success');
        $this->assertSame('Petugas Regu', $staf->fresh()->roleLabel());
        $this->actingAs($this->super)->get(route('admin.users.index'))->assertSee('Petugas Regu')->assertSee('Mengerjakan laporan regu.');
        $this->actingAs($this->super)->put(route('admin.org.role', 'dewa'), ['label' => 'X'])->assertNotFound();
    }

    public function test_csv_import_matches_names_and_rejects_unknown(): void
    {
        $div = Division::create(['name' => 'SDM, Umum & K3']);
        Department::create(['name' => 'Umum & IT', 'division_id' => $div->id]);
        Position::create(['name' => 'Staf IT']);

        // Koma di nama divisi -> pakai kutip. Huruf besar/kecil bebas.
        $csv = "nik,name,role,phone,divisi,bidang,jabatan,regu\n"
            ."8001,Andi,karyawan,,\"sdm, umum & k3\",umum & it,staf it,\n"
            ."8002,Budi,karyawan,,,Keuangan,,\n";
        $file = UploadedFile::fake()->createWithContent('u.csv', $csv);

        $this->actingAs($this->super)->post(route('admin.users.import'), ['file' => $file]);
        $andi = User::where('nik', '8001')->first();
        $this->assertNotNull($andi);
        $this->assertSame(['Umum & IT', 'Staf IT', $div->id], [$andi->department, $andi->position, $andi->division_id]);
        $this->assertNull(User::where('nik', '8002')->first(), 'bidang yang belum terdaftar ditolak');
    }
}
