<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notaries', function (Blueprint $table) {
            $table->string('tin')->nullable()->after('commission_number');
            $table->string('signature_path')->nullable()->after('tin');
        });
    }

    public function down(): void
    {
        Schema::table('notaries', function (Blueprint $table) {
            $table->dropColumn(['tin', 'signature_path']);
        });
    }
};
