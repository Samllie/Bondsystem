<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->date('date_issued')->nullable()->after('project_name');
        });
    }

    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropColumn('date_issued');
        });
    }
};
