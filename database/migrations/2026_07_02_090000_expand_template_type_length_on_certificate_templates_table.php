<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_templates') || ! Schema::hasColumn('certificate_templates', 'template_type')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `certificate_templates` MODIFY `template_type` VARCHAR(64) NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('certificate_templates') || ! Schema::hasColumn('certificate_templates', 'template_type')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `certificate_templates` MODIFY `template_type` VARCHAR(20) NOT NULL');
        }
    }
};
