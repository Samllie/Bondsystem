<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->string('backup_type', 20);
            $table->string('filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('backup_status', 20)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('verification_passed')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_message')->nullable();
            $table->timestamps();

            $table->index('backup_type');
            $table->index('backup_status');
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
    }
};
