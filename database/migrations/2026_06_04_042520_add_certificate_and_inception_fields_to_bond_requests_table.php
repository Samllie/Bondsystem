<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->date('inception_date')->nullable()->after('date_issued');
            $table->string('attention')->nullable()->after('inception_date');
            $table->string('supporting_document_path')->nullable()->after('attention');
            $table->string('certificate_type', 30)->nullable()->after('supporting_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropColumn([
                'inception_date',
                'attention',
                'supporting_document_path',
                'certificate_type',
            ]);
        });
    }
};
