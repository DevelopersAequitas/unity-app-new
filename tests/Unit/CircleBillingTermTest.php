<?php

namespace Tests\Unit;

use App\Enums\CircleBillingTerm;
use PHPUnit\Framework\TestCase;

class CircleBillingTermTest extends TestCase
{
    public function test_term_mapping_is_correct(): void
    {
        $this->assertSame(1, CircleBillingTerm::MONTHLY->months());
        $this->assertSame('M01', CircleBillingTerm::MONTHLY->suffix());

        $this->assertSame(3, CircleBillingTerm::QUARTERLY->months());
        $this->assertSame('M03', CircleBillingTerm::QUARTERLY->suffix());

        $this->assertSame(6, CircleBillingTerm::HALF_YEARLY->months());
        $this->assertSame('M06', CircleBillingTerm::HALF_YEARLY->suffix());

        $this->assertSame(12, CircleBillingTerm::YEARLY->months());
        $this->assertSame('M12', CircleBillingTerm::YEARLY->suffix());
    }
}
