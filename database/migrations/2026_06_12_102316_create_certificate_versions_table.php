<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificate_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bond_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('certificate_type');
            $table->foreignId('template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
            $table->string('docx_path');
            $table->string('pdf_path')->nullable();
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamp('generated_at');
            $table->boolean('is_current')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['bond_request_id', 'version_number']);
            $table->index(['bond_request_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificate_versions');
    }
};
