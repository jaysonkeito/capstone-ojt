<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-role staff: the roles an account holds BEYOND its primary one
 * (users.role stays the priority role). Admins and interns are
 * identities, not hats — they never appear here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['coordinator', 'supervisor', 'dean', 'chair']);
            $table->unique(['user_id', 'role']);
            $table->timestamps();
        });

        // Every existing staff account starts holding exactly its current
        // role — behavior is unchanged until the admin adds more.
        $existing = DB::table('users')
            ->whereIn('role', ['coordinator', 'supervisor', 'dean', 'chair'])
            ->get(['id', 'role']);

        DB::table('user_roles')->insert($existing
            ->map(fn ($user) => [
                'user_id' => $user->id,
                'role' => $user->role,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
