<?php

use App\Enums\CertificateType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->string('party_type', 20)->nullable()->default(null)->change();
        });

        DB::table('bond_requests')
            ->where('certificate_type', CertificateType::CarCertificate->value)
            ->update(['party_type' => null]);
    }

    public function down(): void
    {
        DB::table('bond_requests')
            ->whereNull('party_type')
            ->update(['party_type' => 'private']);

        Schema::table('bond_requests', function (Blueprint $table) {
            $table->string('party_type', 20)->default('private')->nullable(false)->change();
        });
    }
};
