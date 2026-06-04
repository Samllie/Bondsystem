<?php

use App\Models\Maintenance\BondTypeMaster;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        BondTypeMaster::query()
            ->whereNotNull('bond_serial')
            ->each(function (BondTypeMaster $bondType): void {
                $digits = preg_replace('/\D/', '', (string) $bondType->bond_serial) ?? '';
                $bondType->updateQuietly([
                    'bond_serial' => str_pad(substr($digits, 0, 7), 7, '0', STR_PAD_LEFT),
                ]);
            });

        Schema::table('bond_type_masters', function (Blueprint $table) {
            $table->string('bond_serial', 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bond_type_masters', function (Blueprint $table) {
            $table->string('bond_serial', 11)->nullable()->change();
        });
    }
};
