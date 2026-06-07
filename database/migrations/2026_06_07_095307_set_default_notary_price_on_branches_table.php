<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill the sample notary price for every branch.
     */
    public function up(): void
    {
        DB::table('branches')->update(['notary_price' => 500.00]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('branches')->update(['notary_price' => null]);
    }
};
