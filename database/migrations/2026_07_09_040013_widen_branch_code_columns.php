<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('branch_code')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('branch_code')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('branch_code', 3)->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('branch_code', 3)->nullable()->change();
        });
    }
};
