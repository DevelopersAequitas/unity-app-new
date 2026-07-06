<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReferralReportController;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ReferralReportQueryTest extends TestCase
{
    public function test_summary_query_compiles_successfully(): void
    {
        Schema::shouldReceive('hasColumn')
            ->andReturn(true);

        $controller = new ReferralReportController;

        $method = new ReflectionMethod(ReferralReportController::class, 'summaryQuery');
        $method->setAccessible(true);

        $query = $method->invoke($controller, [
            'per_page' => 20,
            'sort' => 'last_referral_date',
            'direction' => 'desc',
        ]);

        $sql = $query->toSql();

        // Assert that referrer_city is selected
        $this->assertStringContainsString('referrer_city', $sql);
        // Assert that referrer.city is in the GROUP BY clause
        $this->assertStringContainsString('referrer.city', $sql);
    }
}
