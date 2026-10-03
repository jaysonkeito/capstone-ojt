<?php

namespace Database\Factories;

use App\Models\InternPersonalInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternPersonalInfo>
 *
 * Defines scalar personal fields only. The caller supplies `user_id` (e.g.
 * `InternPersonalInfo::factory()->create(['user_id' => $intern->id])`), since
 * interns are built through the test `makeIntern()` helper rather than the
 * user factory.
 */
class InternPersonalInfoFactory extends Factory
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
            'birthdate' => fake()->dateTimeBetween('-25 years', '-18 years')->format('Y-m-d'),
            'sex' => fake()->randomElement(['Male', 'Female']),
            'height' => fake()->numberBetween(150, 185).' cm',
            'weight' => fake()->numberBetween(45, 90).' kg',
            'complexion' => fake()->randomElement(['Fair', 'Medium', 'Dark']),
            'disability' => 'None',
            'birth_place' => fake()->city(),
            'citizenship' => 'Filipino',
            'civil_status' => 'Single',
            'present_address' => fake()->address(),
            'present_contact' => fake()->numerify('09#########'),
            'permanent_address' => fake()->address(),
            'permanent_contact' => fake()->numerify('09#########'),
            'father_name' => fake()->name('male'),
            'father_occupation' => fake()->jobTitle(),
            'mother_name' => fake()->name('female'),
            'mother_occupation' => fake()->jobTitle(),
            'parents_address' => fake()->address(),
            'parents_contact' => fake()->numerify('09#########'),
            'guardian_name' => fake()->name(),
            'guardian_contact' => fake()->numerify('09#########'),
            'institutional_email' => fake()->unique()->safeEmail(),
            'phone_number' => fake()->numerify('09#########'),
            'major' => fake()->randomElement(['General Curriculum', 'Multimedia', 'Programming', 'Network Administration']),
            'facebook_link' => 'https://facebook.com/'.fake()->userName(),
            'youtube_link' => 'https://youtube.com/@'.fake()->userName(),
            'linkedin_link' => 'https://linkedin.com/in/'.fake()->userName(),
        ];
    }
}
