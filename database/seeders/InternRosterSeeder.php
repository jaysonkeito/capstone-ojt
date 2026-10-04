<?php

namespace Database\Seeders;

use App\Models\InternPersonalInfo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Bulk-imports interns from database/data/interns.csv — generated from the
 * Registrar's "Official List of Students" (CAS, BSCS & BSINT, 1st Sem SY 2026-2027).
 *
 * Login: Student ID or email ({student_id}@norsubscojt.online)
 * Default password: the intern's last name (e.g. "Teope").
 * Default track: Internship OJT (500 target hours) — adjust per-intern from the Admin panel.
 */
class InternRosterSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/interns.csv');

        if (! file_exists($path)) {
            $this->command?->warn("Roster file not found at {$path} — skipping bulk import.");

            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle); // student_id,last_name,suffix,first_name,sex,course,year_level

        $now = now();
        $rows = [];
        $sexByStudentId = []; // roster sex -> Personal Info rows (drives Mr./Ms. on documents)
        $middleInitialByStudentId = []; // roster middle initials -> Personal Info middle_name
        $hashedNames = []; // cache password hashes per unique last name to avoid re-hashing 597 times

        while (($data = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $data);

            $studentId = trim($record['student_id']);
            $lastName = trim($record['last_name']);
            $firstName = trim($record['first_name']);
            $suffix = trim($record['suffix'] ?? '');

            if ($studentId === '' || $lastName === '') {
                continue;
            }

            // The roster's first_name column carries a trailing middle
            // initial ("Jayson P") — split it off so first_name stays the
            // given name and the initial lands in Personal Info instead.
            $middleInitial = null;
            if (preg_match('/^(.*?)\s+([A-Za-z])\.?$/', $firstName, $m)) {
                $firstName = $m[1];
                $middleInitial = strtoupper($m[2]);
            }

            $fullFirstName = $suffix !== '' ? "{$firstName} {$suffix}" : $firstName;

            $sex = strtoupper(trim($record['sex'] ?? ''));
            $sexByStudentId[$studentId] = $sex === 'M' ? 'Male' : ($sex === 'F' ? 'Female' : null);
            if ($middleInitial !== null) {
                $middleInitialByStudentId[$studentId] = $middleInitial;
            }

            if (! isset($hashedNames[$lastName])) {
                $hashedNames[$lastName] = Hash::make($lastName);
            }

            $rows[] = [
                'role' => 'intern',
                'ojt_track' => 'internship',
                'ojt_status' => 'active',
                'department' => trim($record['course'] ?? '') ?: null,
                'student_id' => $studentId,
                'first_name' => $fullFirstName,
                'last_name' => $lastName,
                'email' => "{$studentId}@norsubscojt.online",
                'password' => $hashedNames[$lastName],
                'target_hours' => 500, // Internship OJT standard
                'is_active' => true,
                // Roster interns are provisioned accounts — skip the gate.
                'profile_completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

        }

        fclose($handle);

        // Insert in chunks and skip duplicates (e.g. if the seeder is re-run).
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('users')->upsert(
                $chunk,
                ['student_id'],
                ['first_name', 'last_name', 'email', 'ojt_track', 'ojt_status', 'department', 'target_hours', 'is_active', 'updated_at']
            );
        }

        // Personal Information rows carrying the roster's sex, so generated
        // documents address every intern correctly (Mr./Ms.) from day one.
        $ids = User::whereIn('student_id', array_keys($sexByStudentId))->pluck('id', 'student_id');
        $personalRows = [];
        foreach ($sexByStudentId as $studentId => $sex) {
            if ($sex && isset($ids[$studentId])) {
                $personalRows[] = ['user_id' => $ids[$studentId], 'sex' => $sex];
            }
        }
        // Middle initials from the roster fill any Personal Info row that has
        // no middle name yet — an intern-entered full middle name always wins.
        foreach ($middleInitialByStudentId as $studentId => $initial) {
            if (! isset($ids[$studentId])) {
                continue;
            }
            $info = InternPersonalInfo::withTrashed()->firstOrNew(['user_id' => $ids[$studentId]]);
            if ($info->middle_name) {
                continue;
            }
            $info->user_id = $ids[$studentId];
            $info->middle_name = $initial;
            $info->save();
        }

        if ($personalRows !== []) {
            foreach (array_chunk($personalRows, 200) as $chunk) {
                DB::table('intern_personal_infos')->upsert($chunk, ['user_id'], ['sex']);
            }
        }

        $this->command?->info(count($rows).' interns imported/updated from the CAS roster.');
    }
}
