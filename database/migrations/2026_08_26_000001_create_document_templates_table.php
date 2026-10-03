<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed Word (.docx) templates for the intern's printable forms.
 * Each `type` (weekly_progress_report, timesheet) can have at most one
 * active template: the admin downloads the shipped starter, edits its
 * layout in Word, and uploads it back. When a row exists the system
 * mail-merges the intern's data into that .docx; when it does not, the
 * form falls back to the hardcoded Blade → PDF design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            // One active template per form type.
            $table->string('type')->unique();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
