<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffProfile>
 *
 * Defines scalar staff details only. The caller supplies `user_id` (e.g.
 * `StaffProfile::factory()->create(['user_id' => $staff->id])`), since staff
 * are built through the test `makeStaff()`/`makeCoordinator()` helpers rather
 * than the user factory.
 */
class StaffProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'middle_name' => fake()->lastName(),
            'prefix_title' => fake()->randomElement(['Mr.', 'Ms.', 'Mrs.', 'Dr.', 'Engr.']),
            'suffix_title' => fake()->optional(0.2)->randomElement(['Jr.', 'Sr.', 'III']),
            'employee_id' => fake()->unique()->numerify('####-####'),
            'institutional_email' => fake()->unique()->safeEmail(),
            'civil_status' => fake()->randomElement(['Single', 'Married', 'Widowed', 'Separated']),
            'designation' => fake()->jobTitle(),
            'date_hired' => fake()->dateTimeBetween('-15 years', '-1 year')->format('Y-m-d'),
            'department' => fake()->randomElement(['CAS', 'BSINT', 'BSIT', 'BSCS']),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'mobile_number' => fake()->numerify('09#########'),
            'qualification' => fake()->sentence(8),
            'specialization' => fake()->words(3, true),
        ];
    }
}
