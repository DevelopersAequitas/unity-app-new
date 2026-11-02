<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceCommissionLedgerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        if (($_ENV['DB_PASSWORD'] ?? '') === 'root' && file_exists(dirname(__DIR__, 3).'/.env')) {
            $envContent = (string) file_get_contents(dirname(__DIR__, 3).'/.env');
            if (preg_match('/^DB_DATABASE=(.+)$/m', $envContent, $dbMatch)) {
                $dbName = trim($dbMatch[1], " \t\n\r\0\x0B\"'");
                $_ENV['DB_DATABASE'] = $dbName;
                $_SERVER['DB_DATABASE'] = $dbName;
                putenv("DB_DATABASE={$dbName}");
            }
            if (preg_match('/^DB_PASSWORD=(.+)$/m', $envContent, $pwMatch)) {
                $dbPass = trim($pwMatch[1], " \t\n\r\0\x0B\"'");
                $_ENV['DB_PASSWORD'] = $dbPass;
                $_SERVER['DB_PASSWORD'] = $dbPass;
                putenv("DB_PASSWORD={$dbPass}");
            }
        }

        parent::setUp();

        $this->ensureMinimalTablesExist();
    }

    private function ensureMinimalTablesExist(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password_hash')->nullable();
                $table->uuid('introduced_by')->nullable();
                $table->uuid('referred_by_user_id')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('status')->default('active');
                $table->boolean('is_assignable')->default(true);
                $table->integer('hierarchy_depth')->default(0);
                $table->decimal('base_amount_inr', 12, 2)->default(18000.00);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('circles')) {
            Schema::create('circles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status')->default('active');
                $table->uuid('circle_founder_user_id')->nullable();
                $table->uuid('circle_director_user_id')->nullable();
                $table->uuid('industry_director_user_id')->nullable();
                $table->uuid('ded_user_id')->nullable();
                $table->uuid('eed_user_id')->nullable();
                $table->uuid('chair_user_id')->nullable();
                $table->uuid('vice_chair_user_id')->nullable();
                $table->uuid('secretary_user_id')->nullable();
                $table->json('calendar')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('circle_members')) {
            Schema::create('circle_members', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('circle_id');
                $table->uuid('user_id');
                $table->uuid('role_id')->nullable();
                $table->string('role')->default('member');
                $table->string('status')->default('approved');
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('circle_id')->nullable();
                $table->decimal('amount', 12, 2)->default(18000.00);
                $table->decimal('base_amount', 12, 2)->default(18000.00);
                $table->decimal('total_amount', 12, 2)->default(18000.00);
                $table->string('currency')->default('INR');
                $table->string('status')->default('paid');
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        $migration = require base_path('database/migrations/2026_11_02_123000_create_finance_commission_and_ledger_tables.php');
        $migration->up();
    }

    public function test_cd_policy_specification_matches_executive_schema(): void
    {
        $admin = $this->createPeer('Admin', 'User', 'admin@peersglobal.com');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/finance/policy/CD');

        $response->assertOk()
            ->assertJsonPath('role', 'Circle Director')
            ->assertJsonPath('role_code', 'CD')
            ->assertJsonPath('base_amount_inr', 18000);

        $rules = collect($response->json('commission_rules'))->keyBy('code');

        $this->assertSame('RENEWAL_EXISTING', $rules['A']['channel']);
        $this->assertEquals(25.0, $rules['A']['percentage']);
        $this->assertEquals(4500.0, $rules['A']['payout_amount']);

        $this->assertSame('DIRECT_EFFORT', $rules['B']['channel']);
        $this->assertEquals(25.0, $rules['B']['percentage']);
        $this->assertEquals(4500.0, $rules['B']['payout_amount']);

        $this->assertSame('MEMBER_REFERRAL', $rules['C']['channel']);
        $this->assertEquals(20.0, $rules['C']['percentage']);
        $this->assertEquals(3600.0, $rules['C']['payout_amount']);

        $this->assertSame('TEAM_ASSIGNED', $rules['D']['channel']);
        $this->assertEquals(10.0, $rules['D']['percentage']);
        $this->assertEquals(1800.0, $rules['D']['payout_amount']);
    }

    public function test_four_conditions_and_six_month_leader_tenure_rotation_with_bank_passbook(): void
    {
        $leaderAmit = $this->createPeer('Amit', 'Patel', 'amit.cd@peersglobal.com');
        $leaderPriya = $this->createPeer('Priya', 'Shah', 'priya.cd@peersglobal.com');
        Sanctum::actingAs($leaderAmit);

        // 1. Create Circle with Leader Amit as Circle Director (Tenure 1)
        $circlePayload = [
            'id' => (string) Str::uuid(),
            'name' => 'SG Highway Circle',
            'slug' => 'sg-highway-circle',
            'status' => 'active',
            'calendar' => [
                'leadership' => [
                    'circle_director_user_id' => $leaderAmit->id,
                    'director_user_id' => $leaderAmit->id,
                ],
            ],
        ];
        if (Schema::hasColumn('circles', 'circle_director_user_id')) {
            $circlePayload['circle_director_user_id'] = $leaderAmit->id;
        }
        if (Schema::hasColumn('circles', 'director_user_id')) {
            $circlePayload['director_user_id'] = $leaderAmit->id;
        }
        $circle = Circle::query()->create($circlePayload);

        // Verify Tenure 1 is automatically opened for Amit
        $this->assertDatabaseHas('finance_leader_tenures', [
            'user_id' => $leaderAmit->id,
            'role_code' => 'CD',
            'scope_id' => $circle->id,
            'is_active' => true,
        ]);

        // 2. Condition B (DIRECT_EFFORT): Peer Rohan is introduced directly by CD Amit (25% = ₹4,500)
        $peerRohan = $this->createPeer('Rohan', 'Mehta', 'rohan@peersglobal.com', $leaderAmit->id);
        DB::table('circle_members')->insert([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'user_id' => $peerRohan->id,
            'role' => 'member',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resB = $this->postJson('/api/v1/admin/finance/process-payment', [
            'member_user_id' => $peerRohan->id,
            'circle_id' => $circle->id,
            'base_amount' => 18000,
            'role_code' => 'CD',
        ])->assertCreated();

        $this->assertSame('B', $resB->json('data.transactions.0.category_code'));
        $this->assertSame('DIRECT_EFFORT', $resB->json('data.transactions.0.channel'));
        $this->assertEquals(4500.0, $resB->json('data.transactions.0.credited_amount'));
        $this->assertEquals(0.0, $resB->json('data.transactions.0.opening_balance'));
        $this->assertEquals(4500.0, $resB->json('data.transactions.0.closing_balance'));

        // 3. Condition C (MEMBER_REFERRAL): Peer Karan is introduced by Circle Member Rohan (20% = ₹3,600)
        $peerKaran = $this->createPeer('Karan', 'Desai', 'karan@peersglobal.com', $peerRohan->id);
        $resC = $this->postJson('/api/v1/admin/finance/process-payment', [
            'member_user_id' => $peerKaran->id,
            'circle_id' => $circle->id,
            'base_amount' => 18000,
            'role_code' => 'CD',
        ])->assertCreated();

        $this->assertSame('C', $resC->json('data.transactions.0.category_code'));
        $this->assertSame('MEMBER_REFERRAL', $resC->json('data.transactions.0.channel'));
        $this->assertEquals(3600.0, $resC->json('data.transactions.0.credited_amount'));
        $this->assertEquals(4500.0, $resC->json('data.transactions.0.opening_balance'));
        $this->assertEquals(8100.0, $resC->json('data.transactions.0.closing_balance'));

        $walletId = (string) $resC->json('data.transactions.0.wallet_id');

        // 4. Withdrawal (DEBIT): Amit withdraws ₹3,000 from Circle Budget
        $wdrResponse = $this->postJson('/api/v1/admin/finance/withdrawals', [
            'wallet_id' => $walletId,
            'leader_user_id' => $leaderAmit->id,
            'amount' => 3000,
            'purpose' => 'Circle Monthly Networking Mixer',
            'payout_method' => 'bank_transfer',
        ])->assertCreated();

        $withdrawalId = (string) $wdrResponse->json('data.id');

        $approveResponse = $this->postJson("/api/v1/admin/finance/withdrawals/{$withdrawalId}/approve", [
            'payout_method' => 'bank_transfer',
            'payout_reference_utr' => 'UTR9988776655',
        ])->assertOk();

        $this->assertSame('paid', $approveResponse->json('data.status'));
        $this->assertEquals(5100.0, $approveResponse->json('data.wallet.current_balance'));

        // 5. 6-Month Leader Rotation: Priya replaces Amit as Circle Director of SG Highway Circle
        $circle->calendarSet('leadership.circle_director_user_id', $leaderPriya->id);
        $circle->calendarSet('leadership.director_user_id', $leaderPriya->id);
        if (Schema::hasColumn('circles', 'circle_director_user_id')) {
            $circle->circle_director_user_id = $leaderPriya->id;
        }
        if (Schema::hasColumn('circles', 'director_user_id')) {
            $circle->director_user_id = $leaderPriya->id;
        }
        $circle->save();

        // Verify Amit's tenure is now historical (is_active = false) and Priya's tenure is active (is_active = true)
        $this->assertDatabaseHas('finance_leader_tenures', [
            'user_id' => $leaderAmit->id,
            'role_code' => 'CD',
            'scope_id' => $circle->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('finance_leader_tenures', [
            'user_id' => $leaderPriya->id,
            'role_code' => 'CD',
            'scope_id' => $circle->id,
            'is_active' => true,
        ]);

        // 6. Condition D (TEAM_ASSIGNED): Peer Meera joins without a circle referrer (assigned by Peers Global Team -> 10% = ₹1,800)
        $peerMeera = $this->createPeer('Meera', 'Joshi', 'meera@peersglobal.com', null);
        $resD = $this->postJson('/api/v1/admin/finance/process-payment', [
            'member_user_id' => $peerMeera->id,
            'circle_id' => $circle->id,
            'base_amount' => 18000,
            'role_code' => 'CD',
        ])->assertCreated();

        $this->assertSame('D', $resD->json('data.transactions.0.category_code'));
        $this->assertSame('TEAM_ASSIGNED', $resD->json('data.transactions.0.channel'));
        $this->assertSame($leaderPriya->id, $resD->json('data.transactions.0.leader_user_id'));
        $this->assertEquals(1800.0, $resD->json('data.transactions.0.credited_amount'));
        $this->assertEquals(5100.0, $resD->json('data.transactions.0.opening_balance'));
        $this->assertEquals(6900.0, $resD->json('data.transactions.0.closing_balance'));

        // 7. Condition A (RENEWAL_EXISTING): Peer Rohan renews his membership (25% = ₹4,500)
        $resA = $this->postJson('/api/v1/admin/finance/process-payment', [
            'member_user_id' => $peerRohan->id,
            'circle_id' => $circle->id,
            'base_amount' => 18000,
            'is_renewal' => true,
            'role_code' => 'CD',
        ])->assertCreated();

        $this->assertSame('A', $resA->json('data.transactions.0.category_code'));
        $this->assertSame('RENEWAL_EXISTING', $resA->json('data.transactions.0.channel'));
        $this->assertSame($leaderPriya->id, $resA->json('data.transactions.0.leader_user_id'));
        $this->assertEquals(4500.0, $resA->json('data.transactions.0.credited_amount'));
        $this->assertEquals(6900.0, $resA->json('data.transactions.0.opening_balance'));
        $this->assertEquals(11400.0, $resA->json('data.transactions.0.closing_balance'));

        // 8. Verify Tenure-wise Financial Attribution Report (/api/v1/admin/finance/tenures)
        $tenuresResponse = $this->getJson('/api/v1/admin/finance/tenures?role_code=CD&scope_id='.$circle->id)
            ->assertOk();

        $tenuresByUser = collect($tenuresResponse->json('data'))->keyBy('user_id');

        // Amit's Tenure: +8100 credited, -3000 withdrawn, net +5100
        $this->assertFalse($tenuresByUser[$leaderAmit->id]['is_active']);
        $this->assertEquals(8100.0, $tenuresByUser[$leaderAmit->id]['financial_summary']['total_credited_inr']);
        $this->assertEquals(3000.0, $tenuresByUser[$leaderAmit->id]['financial_summary']['total_withdrawn_inr']);
        $this->assertEquals(5100.0, $tenuresByUser[$leaderAmit->id]['financial_summary']['net_budget_generated_inr']);

        // Priya's Tenure: +6300 credited (1800 + 4500), 0 withdrawn, net +6300
        $this->assertTrue($tenuresByUser[$leaderPriya->id]['is_active']);
        $this->assertEquals(6300.0, $tenuresByUser[$leaderPriya->id]['financial_summary']['total_credited_inr']);
        $this->assertEquals(0.0, $tenuresByUser[$leaderPriya->id]['financial_summary']['total_withdrawn_inr']);
        $this->assertEquals(6300.0, $tenuresByUser[$leaderPriya->id]['financial_summary']['net_budget_generated_inr']);
    }

    private function createPeer(string $first, string $last, string $email, ?string $introducedBy = null): User
    {
        $cols = Schema::getColumnListing('users');

        $payload = [
            'id' => (string) Str::uuid(),
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => "{$first} {$last}",
            'email' => $email,
            'password_hash' => bcrypt('password'),
            'introduced_by' => $introducedBy,
            'referred_by_user_id' => $introducedBy,
            'status' => 'active',
        ];

        return User::query()->create(array_intersect_key($payload, array_flip($cols)));
    }
}
