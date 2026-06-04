<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->string('expiry_date', 500)->change();
        });
    }

    public function down(): void
    {
        DB::table('bond_requests')
            ->whereRaw("expiry_date NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->update(['expiry_date' => null]);

        Schema::table('bond_requests', function (Blueprint $table) {
            $table->date('expiry_date')->change();
        });
    }
};
