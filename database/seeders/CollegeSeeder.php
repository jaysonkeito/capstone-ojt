<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CollegeSeeder extends Seeder
{
    /**
     * The colleges the installation recognizes. CAS ships by default; add a
     * row (code + name) per additional college and its Templates tab appears.
     */
    public function run(): void
    {
        DB::table('colleges')->upsert([
            ['code' => 'cas', 'name' => 'College of Arts and Sciences'],
        ], ['code'], ['name']);
    }
}
