<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('branch_city')->nullable()->after('branch_code');
        });

        DB::table('branches')
            ->whereNull('branch_city')
            ->whereNotNull('address')
            ->update(['branch_city' => DB::raw('address')]);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('branch_city');
        });
    }
};
