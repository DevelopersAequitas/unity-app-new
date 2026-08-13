<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Resources\CircleResource;
use App\Models\Circle;
use Illuminate\Http\Request;
use Tests\TestCase;

class CircleMeetingDetailsTest extends TestCase
{
    public function test_circle_model_accessors_and_resource_include_meeting_details(): void
    {
        $circle = new Circle([
            'name' => 'Meeting Details Circle',
            'slug' => 'meeting-details-circle',
            'status' => 'active',
            'type' => 'public',
            'calendar' => [
                'settings' => [
                    'meeting_mode' => 'hybrid',
                    'meeting_link' => 'https://zoom.us/j/987654321',
                    'meeting_passcode' => 'Pass123',
                    'meeting_venue' => 'Grand Palace Hotel, Main Conference Room',
                    'meeting_landmark' => 'Near Central Park',
                ],
            ],
        ]);
        $circle->id = 'circle-meeting-1';
        $circle->setRelation('members', collect());

        $this->assertSame('https://zoom.us/j/987654321', $circle->meeting_link);
        $this->assertSame('Pass123', $circle->meeting_passcode);
        $this->assertSame('Grand Palace Hotel, Main Conference Room', $circle->meeting_venue);
        $this->assertSame('Near Central Park', $circle->meeting_landmark);

        $resourceArray = (new CircleResource($circle))->toArray(Request::create('/'));

        $this->assertSame('https://zoom.us/j/987654321', data_get($resourceArray, 'meeting_link'));
        $this->assertSame('Pass123', data_get($resourceArray, 'meeting_passcode'));
        $this->assertSame('Grand Palace Hotel, Main Conference Room', data_get($resourceArray, 'meeting_venue'));
        $this->assertSame('Near Central Park', data_get($resourceArray, 'meeting_landmark'));
    }
}
