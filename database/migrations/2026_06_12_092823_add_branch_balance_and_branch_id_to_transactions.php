<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('notary_price');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('user_id')
                ->constrained('branches')
                ->nullOnDelete();
        });

        $branchTotals = DB::table('users')
            ->whereNotNull('branch_id')
            ->select('branch_id', DB::raw('SUM(balance) as total_balance'))
            ->groupBy('branch_id')
            ->get();

        foreach ($branchTotals as $row) {
            DB::table('branches')
                ->where('id', $row->branch_id)
                ->update(['balance' => $row->total_balance]);
        }

        DB::table('users')->update(['balance' => 0]);

        $transactionBranches = DB::table('transactions')
            ->join('users', 'transactions.user_id', '=', 'users.id')
            ->whereNotNull('users.branch_id')
            ->select('transactions.id', 'users.branch_id')
            ->get();

        foreach ($transactionBranches as $row) {
            DB::table('transactions')
                ->where('id', $row->id)
                ->update(['branch_id' => $row->branch_id]);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
