<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->string('authorized_representative')->nullable()->after('car');
            $table->string('tin', 20)->nullable()->after('authorized_representative');
        });
    }

    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropColumn(['authorized_representative', 'tin']);
        });
    }
};
