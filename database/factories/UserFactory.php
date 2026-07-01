<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current hashed password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $displayName = trim($firstName.' '.$lastName);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $displayName,
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->numerify('##########'),
            'company_name' => $this->faker->company(),
            'membership_status' => 'visitor',
            'coins_balance' => 0,
            'password_hash' => static::$password ??= Hash::make('password'),
            'public_profile_slug' => Str::slug($displayName).'-'.Str::random(6),
        ];
    }
}
