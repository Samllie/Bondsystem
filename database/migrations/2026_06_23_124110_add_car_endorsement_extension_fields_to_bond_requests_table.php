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
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->date('extension_period_start')->nullable()->after('date_issued');
            $table->string('validity_extension', 255)->nullable()->after('extension_period_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropColumn(['extension_period_start', 'validity_extension']);
        });
    }
};
