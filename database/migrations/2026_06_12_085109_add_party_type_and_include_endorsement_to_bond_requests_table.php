<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->string('party_type', 20)->default('private')->after('certificate_type');
            $table->boolean('include_endorsement_number')->default(false)->after('endorsement_number');
        });
    }

    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropColumn(['party_type', 'include_endorsement_number']);
        });
    }
};
