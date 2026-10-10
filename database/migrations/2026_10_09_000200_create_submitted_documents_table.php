<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requirement documents an intern submits back for their coordinator to
     * review — the other half of the Requirements page (download there,
     * submit here). One row per submission; the latest submission per
     * document type is the one that counts, so a rejected file can be
     * replaced without losing the review trail.
     */
    public function up(): void
    {
        Schema::create('submitted_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The DocumentTemplate requirement type this file answers.
            $table->string('type', 60);
            $table->string('file_path');
            $table->string('original_name');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submitted_documents');
    }
};
