<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->json('supporting_document_paths')->nullable()->after('attention');
        });

        DB::table('bond_requests')
            ->whereNotNull('supporting_document_path')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $row): void {
                DB::table('bond_requests')
                    ->where('id', $row->id)
                    ->update([
                        'supporting_document_paths' => json_encode([$row->supporting_document_path]),
                    ]);
            });

        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropColumn('supporting_document_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bond_requests', function (Blueprint $table) {
            $table->string('supporting_document_path')->nullable()->after('attention');
        });

        DB::table('bond_requests')
            ->whereNotNull('supporting_document_paths')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $row): void {
                $paths = json_decode($row->supporting_document_paths, true);
                $firstPath = is_array($paths) ? ($paths[0] ?? null) : null;

                DB::table('bond_requests')
                    ->where('id', $row->id)
                    ->update(['supporting_document_path' => $firstPath]);
            });

        Schema::table('bond_requests', function (Blueprint $table) {
            $table->dropColumn('supporting_document_paths');
        });
    }
};
