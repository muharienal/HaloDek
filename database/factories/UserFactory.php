<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'nik' => (string) fake()->unique()->numerify('1#####'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'department' => fake()->randomElement(['Finance', 'HRD', 'Operasional', 'Marketing', 'Produksi']),
            'role' => User::ROLE_KARYAWAN,
            'password' => 'password',
            'must_change_password' => false,
            'is_active' => true,
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }
}
