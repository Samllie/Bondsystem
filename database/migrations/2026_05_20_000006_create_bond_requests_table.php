<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bond_requests', function (Blueprint $table) {
            $table->id();
            $table->string('bond_number')->unique();
            $table->string('bond_type');
            $table->foreignId('principal_id')->constrained()->restrictOnDelete();
            $table->foreignId('obligee_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->date('expiry_date');
            $table->date('request_date');
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('bond_type');
            $table->index('request_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bond_requests');
    }
};
