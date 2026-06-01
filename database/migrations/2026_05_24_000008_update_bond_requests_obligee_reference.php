<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropForeign(['obligee_id']);
            $table->string('obligee_name')->nullable()->after('obligee_id');
        });

        if (Schema::hasTable('obligees') && Schema::getConnection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement(
                'UPDATE bond_requests INNER JOIN obligees ON bond_requests.obligee_id = obligees.id SET bond_requests.obligee_name = obligees.company_name WHERE bond_requests.obligee_name IS NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropColumn('obligee_name');
            $table->foreign('obligee_id')->references('id')->on('obligees')->restrictOnDelete();
        });
    }
};
