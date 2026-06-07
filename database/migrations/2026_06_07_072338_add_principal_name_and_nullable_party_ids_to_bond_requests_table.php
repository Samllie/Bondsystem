<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->string('principal_name')->nullable()->after('principal_id');
        });

        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropForeign(['principal_id']);
        });

        Schema::table('bond_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('principal_id')->nullable()->change();
            $table->unsignedBigInteger('obligee_id')->nullable()->change();
            $table->foreign('principal_id')->references('id')->on('principals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropForeign(['principal_id']);
        });

        Schema::table('bond_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('principal_id')->nullable(false)->change();
            $table->unsignedBigInteger('obligee_id')->nullable(false)->change();
            $table->foreign('principal_id')->references('id')->on('principals')->restrictOnDelete();
            $table->dropColumn('principal_name');
        });
    }
};
