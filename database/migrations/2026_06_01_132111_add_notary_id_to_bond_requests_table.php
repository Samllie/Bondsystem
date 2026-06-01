<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->foreignId('notary_id')
                ->nullable()
                ->after('signatory_position')
                ->constrained('notaries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('notary_id');
        });
    }
};
