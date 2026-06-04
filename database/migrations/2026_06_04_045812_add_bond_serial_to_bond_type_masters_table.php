<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_type_masters', function (Blueprint $table) {
            $table->string('bond_serial', 11)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('bond_type_masters', function (Blueprint $table) {
            $table->dropColumn('bond_serial');
        });
    }
};
