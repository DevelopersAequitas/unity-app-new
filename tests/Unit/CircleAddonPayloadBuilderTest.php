<?php

namespace Tests\Unit;

use App\Enums\CircleBillingTerm;
use App\Models\Circle;
use App\Services\Zoho\CircleAddonPayloadBuilder;
use PHPUnit\Framework\TestCase;

class CircleAddonPayloadBuilderTest extends TestCase
{
    public function test_sync_hash_changes_when_price_changes(): void
    {
        $builder = new CircleAddonPayloadBuilder();
        $circle = new Circle();
        $circle->id = '415be33e-952e-4f8d-8d62-0d0bff38f6cd';
        $circle->name = 'My Circle';

        $payloadA = $builder->build($circle, CircleBillingTerm::MONTHLY, 'CRCL_415BE33E952E_M01', 100);
        $payloadB = $builder->build($circle, CircleBillingTerm::MONTHLY, 'CRCL_415BE33E952E_M01', 200);

        $hashA = $builder->syncHash($circle, CircleBillingTerm::MONTHLY, $payloadA, true);
        $hashB = $builder->syncHash($circle, CircleBillingTerm::MONTHLY, $payloadB, true);

        $this->assertNotSame($hashA, $hashB);
    }
}
