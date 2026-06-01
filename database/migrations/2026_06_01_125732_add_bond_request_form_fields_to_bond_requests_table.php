<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->foreignId('bond_type_id')
                ->nullable()
                ->after('bond_number')
                ->constrained('bond_type_masters')
                ->nullOnDelete();

            $table->string('address_1')->nullable()->after('obligee_name');
            $table->string('address_2')->nullable()->after('address_1');
            $table->string('address_3')->nullable()->after('address_2');
            $table->string('amount_in_words')->nullable()->after('amount');
            $table->string('project_name')->nullable()->after('amount_in_words');

            $table->foreignId('signatory_id')
                ->nullable()
                ->after('project_name')
                ->constrained('signatories')
                ->nullOnDelete();

            $table->string('signatory_position')->nullable()->after('signatory_id');
            $table->string('doc_no')->nullable()->after('signatory_position');
            $table->string('page_no')->nullable()->after('doc_no');
            $table->string('book_no')->nullable()->after('page_no');
            $table->string('series_year', 4)->nullable()->after('book_no');
        });
    }

    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bond_type_id');
            $table->dropConstrainedForeignId('signatory_id');
            $table->dropColumn([
                'address_1',
                'address_2',
                'address_3',
                'amount_in_words',
                'project_name',
                'signatory_position',
                'doc_no',
                'page_no',
                'book_no',
                'series_year',
            ]);
        });
    }
};
