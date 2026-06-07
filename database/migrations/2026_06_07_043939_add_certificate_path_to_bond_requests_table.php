<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->string('docx_path')->nullable()->after('supporting_document_path');
            $table->string('certificate_path')->nullable()->after('docx_path');
        });
    }

    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropColumn(['docx_path', 'certificate_path']);
        });
    }
};
