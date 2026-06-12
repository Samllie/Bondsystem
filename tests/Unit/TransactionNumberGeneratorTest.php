<?php

namespace Tests\Unit;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_numbers_are_unique_across_users(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $first = Transaction::create([
            'user_id' => $firstUser->id,
            'type' => TransactionType::Credit->value,
            'amount' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'description' => 'First credit',
        ]);

        $second = Transaction::create([
            'user_id' => $secondUser->id,
            'type' => TransactionType::Credit->value,
            'amount' => 200,
            'balance_before' => 0,
            'balance_after' => 200,
            'description' => 'Second credit',
        ]);

        $this->assertNotSame($first->transaction_number, $second->transaction_number);
        $this->assertSame(1, Transaction::query()->where('transaction_number', $first->transaction_number)->count());
        $this->assertSame(1, Transaction::query()->where('transaction_number', $second->transaction_number)->count());
    }

    public function test_generated_numbers_increment_for_the_same_day(): void
    {
        $user = User::factory()->create();

        $first = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Credit->value,
            'amount' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'description' => 'First credit',
        ]);

        $second = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::Debit->value,
            'amount' => 50,
            'balance_before' => 100,
            'balance_after' => 50,
            'description' => 'First debit',
        ]);

        $prefix = 'TXN-'.now()->format('Ymd').'-';

        $this->assertStringStartsWith($prefix, $first->transaction_number);
        $this->assertStringStartsWith($prefix, $second->transaction_number);

        $firstSequence = (int) substr($first->transaction_number, strlen($prefix));
        $secondSequence = (int) substr($second->transaction_number, strlen($prefix));

        $this->assertSame($firstSequence + 1, $secondSequence);
    }
}
