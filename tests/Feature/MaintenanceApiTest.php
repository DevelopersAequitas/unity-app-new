<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppMaintenance;
use App\Services\MaintenanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('app_maintenances');

        Schema::create('app_maintenances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status', 32)->default('none');
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('support_email')->nullable();
            $table->timestamp('fcm_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_public_maintenance_endpoint_returns_none_status(): void
    {
        $response = $this->getJson('/api/v1/app/maintenance');

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Maintenance status fetched',
                'data' => [
                    'status' => 'none',
                    'title' => '',
                    'message' => '',
                    'start_time' => null,
                    'end_time' => null,
                    'duration_minutes' => null,
                ],
            ]);
    }

    public function test_public_maintenance_endpoint_returns_scheduled_maintenance(): void
    {
        $startTime = Carbon::now()->addHour();
        $endTime = Carbon::now()->addHours(3);

        AppMaintenance::query()->create([
            'status' => 'scheduled',
            'title' => 'Scheduled Maintenance',
            'message' => 'Upgrading servers.',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => 120,
            'support_email' => 'support@peersunity.com',
        ]);

        $response = $this->getJson('/api/v1/app/maintenance');

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Maintenance status fetched',
                'data' => [
                    'status' => 'scheduled',
                    'title' => 'Scheduled Maintenance',
                    'message' => 'Upgrading servers.',
                    'duration_minutes' => 120,
                    'support_email' => 'support@peersunity.com',
                ],
            ]);
    }

    public function test_public_maintenance_endpoint_returns_active_maintenance(): void
    {
        $startTime = Carbon::now()->subHour();
        $endTime = Carbon::now()->addHour();

        AppMaintenance::query()->create([
            'status' => 'active',
            'title' => 'We’re under maintenance',
            'message' => 'Improving the platform.',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => 120,
            'support_email' => 'support@peersunity.com',
        ]);

        $response = $this->getJson('/api/v1/app/maintenance');

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Maintenance status fetched',
                'data' => [
                    'status' => 'active',
                    'title' => 'We’re under maintenance',
                    'message' => 'Improving the platform.',
                    'duration_minutes' => 120,
                    'support_email' => 'support@peersunity.com',
                ],
            ]);
    }

    public function test_maintenance_service_transitions_scheduled_to_active(): void
    {
        $startTime = Carbon::now()->subMinutes(5);
        $endTime = Carbon::now()->addHour();

        $maintenance = AppMaintenance::query()->create([
            'status' => 'scheduled',
            'title' => 'Scheduled Maintenance',
            'message' => 'Server maintenance.',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => 65,
        ]);

        $service = app(MaintenanceService::class);
        $service->processMaintenanceTransitions();

        $this->assertDatabaseHas('app_maintenances', [
            'id' => $maintenance->id,
            'status' => 'active',
        ]);
    }

    public function test_maintenance_service_transitions_active_to_completed(): void
    {
        $startTime = Carbon::now()->subHours(2);
        $endTime = Carbon::now()->subMinutes(5);

        $maintenance = AppMaintenance::query()->create([
            'status' => 'active',
            'title' => 'Active Maintenance',
            'message' => 'Server maintenance.',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => 115,
        ]);

        $service = app(MaintenanceService::class);
        $service->processMaintenanceTransitions();

        $this->assertDatabaseHas('app_maintenances', [
            'id' => $maintenance->id,
            'status' => 'completed',
        ]);
    }
}
