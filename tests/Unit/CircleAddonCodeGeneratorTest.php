<?php

namespace Tests\Unit;

use App\Enums\CircleBillingTerm;
use App\Models\Circle;
use App\Services\Zoho\CircleAddonCodeGenerator;
use Tests\TestCase;

class CircleAddonCodeGeneratorTest extends TestCase
{
    public function test_it_generates_stable_codes_from_circle_uuid(): void
    {
        $circle = new Circle();
        $circle->id = '415be33e-952e-4f8d-8d62-0d0bff38f6cd';

        $generator = new CircleAddonCodeGenerator();

        $this->assertSame('CRCL_415BE33E952E_M01', $generator->generate($circle, CircleBillingTerm::MONTHLY));
        $this->assertSame('CRCL_415BE33E952E_M03', $generator->generate($circle, CircleBillingTerm::QUARTERLY));
        $this->assertSame('CRCL_415BE33E952E_M06', $generator->generate($circle, CircleBillingTerm::HALF_YEARLY));
        $this->assertSame('CRCL_415BE33E952E_M12', $generator->generate($circle, CircleBillingTerm::YEARLY));
    }
}
