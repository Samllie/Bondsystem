<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_versions', function (Blueprint $table) {
            $table->string('confirmation_number')->nullable()->after('remarks');
            $table->string('verification_token', 64)->nullable()->after('confirmation_number');
            $table->string('qr_code_path')->nullable()->after('verification_token');
            $table->unsignedInteger('verification_count')->default(0)->after('qr_code_path');
            $table->timestamp('last_verified_at')->nullable()->after('verification_count');

            $table->unique('confirmation_number');
            $table->unique('verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_versions', function (Blueprint $table) {
            $table->dropUnique(['confirmation_number']);
            $table->dropUnique(['verification_token']);
            $table->dropColumn([
                'confirmation_number',
                'verification_token',
                'qr_code_path',
                'verification_count',
                'last_verified_at',
            ]);
        });
    }
};
