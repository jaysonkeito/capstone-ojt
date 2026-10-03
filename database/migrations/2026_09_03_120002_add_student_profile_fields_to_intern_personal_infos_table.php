<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the student (intern) profile completion form collects that the
 * requirement-form sheet never needed: an institutional email for online
 * meeting verification, a primary phone number, the optional program major,
 * and social profile links. They live alongside the existing personal
 * details (birthdate, guardian, address …) the completion form also fills.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intern_personal_infos', function (Blueprint $table) {
            $table->string('institutional_email')->nullable()->after('guardian_contact');
            $table->string('phone_number')->nullable()->after('institutional_email');
            $table->string('major')->nullable()->after('phone_number');
            $table->string('facebook_link')->nullable()->after('major');
            $table->string('youtube_link')->nullable()->after('facebook_link');
            $table->string('linkedin_link')->nullable()->after('youtube_link');
        });
    }

    public function down(): void
    {
        Schema::table('intern_personal_infos', function (Blueprint $table) {
            $table->dropColumn([
                'institutional_email',
                'phone_number',
                'major',
                'facebook_link',
                'youtube_link',
                'linkedin_link',
            ]);
        });
    }
};
