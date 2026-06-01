<?php

use App\Models\BondRequest;
use App\Models\PaymentHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_histories', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bond_request_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('user_id');
            $table->index('paid_at');
        });

        foreach (BondRequest::query()->whereNotNull('created_by')->cursor() as $bondRequest) {
            PaymentHistory::create([
                'user_id' => $bondRequest->created_by,
                'bond_request_id' => $bondRequest->id,
                'amount' => $bondRequest->amount,
                'description' => "Bond request payment — {$bondRequest->bond_number}",
                'paid_at' => $bondRequest->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_histories');
    }
};
