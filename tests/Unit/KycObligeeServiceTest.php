<?php

namespace Tests\Unit;

use App\Services\KycObligeeService;
use Tests\TestCase;

class KycObligeeServiceTest extends TestCase
{
    public function test_find_returns_null_for_invalid_ids_without_querying(): void
    {
        $service = new KycObligeeService;

        $this->assertNull($service->find(0));
        $this->assertNull($service->find(-1));
    }

    public function test_kyc_id_column_defaults_to_client_id(): void
    {
        $this->assertSame('client_id', config('kyc.columns.id'));
    }
}
