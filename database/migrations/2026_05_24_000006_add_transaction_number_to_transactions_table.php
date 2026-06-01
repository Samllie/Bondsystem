<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transaction_number', 30)->nullable()->unique()->after('id');
        });

        $sequence = 1;

        foreach (\App\Models\Transaction::orderBy('id')->cursor() as $transaction) {
            $transaction->update([
                'transaction_number' => sprintf(
                    'TXN-%s-%05d',
                    $transaction->created_at->format('Ymd'),
                    $sequence++,
                ),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['transaction_number']);
            $table->dropColumn('transaction_number');
        });
    }
};
