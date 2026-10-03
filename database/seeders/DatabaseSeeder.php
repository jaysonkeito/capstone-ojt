<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Admin account ------------------------------------------------
        User::updateOrCreate(
            ['email' => 'norsubscojt@gmail.com'],
            [
                'role' => 'admin',
                'student_id' => null,
                'first_name' => null,
                'last_name' => 'OJT Admin',
                'password' => Hash::make('norsubscojt2026'),
                'password_changed_at' => now(),
                'target_hours' => 0,
                'is_active' => true,
            ]
        );

        // --- Single test intern account ------------------------------------
        // Matches the example in the spec: login "TEOPE" / password "Teope".
        User::updateOrCreate(
            ['student_id' => '202300524'],
            [
                'role' => 'intern',
                'ojt_track' => 'internship',
                'ojt_status' => 'active',
                'department' => 'BSINT',
                'first_name' => 'John Ryan V',
                'last_name' => 'Teope',
                'email' => '202300524@norsubscojt.online',
                'password' => Hash::make('Teope'),
                'target_hours' => 500,
                'is_active' => true,
                // Provisioned demo account — skips the first-login profile gate.
                'profile_completed_at' => now(),
            ]
        );

        // --- Full CAS intern roster (from the Registrar's official list) --
        // Comment this out if you only want the single test account above.
        $this->call(InternRosterSeeder::class);

        // --- Offices + monitoring staff (coordinator / supervisors) -------
        $mis = Office::updateOrCreate(
            ['name' => 'NORSU-BSC MIS Office'],
            ['type' => 'internal', 'address' => 'NORSU Bais City Campus', 'is_active' => true],
        );

        $cityHall = Office::updateOrCreate(
            ['name' => 'Dumaguete City Hall — Mayor\'s Office'],
            ['type' => 'external', 'address' => 'Dumaguete City, Negros Oriental', 'is_active' => true],
        );

        User::updateOrCreate(
            ['email' => 'coordinator@norsubscojt.online'],
            [
                'role' => 'coordinator',
                'student_id' => null,
                'first_name' => 'OJT',
                'last_name' => 'Coordinator',
                'password' => Hash::make('Coordinator2026'),
                'password_changed_at' => now(),
                'target_hours' => 0,
                'is_active' => true,
                // Provisioned demo account — skips the first-login profile gate.
                'profile_completed_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'mis.supervisor@norsubscojt.online'],
            [
                'role' => 'supervisor',
                'student_id' => null,
                'first_name' => 'MIS',
                'last_name' => 'Supervisor',
                'password' => Hash::make('Supervisor2026'),
                'password_changed_at' => now(),
                'office_id' => $mis->id,
                'target_hours' => 0,
                'is_active' => true,
                // Provisioned demo account — skips the first-login profile gate.
                'profile_completed_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'cityhall.supervisor@norsubscojt.online'],
            [
                'role' => 'supervisor',
                'student_id' => null,
                'first_name' => 'City Hall',
                'last_name' => 'Supervisor',
                'password' => Hash::make('Supervisor2026'),
                'password_changed_at' => now(),
                'office_id' => $cityHall->id,
                'target_hours' => 0,
                'is_active' => true,
                // Provisioned demo account — skips the first-login profile gate.
                'profile_completed_at' => now(),
            ]
        );
    }
}
