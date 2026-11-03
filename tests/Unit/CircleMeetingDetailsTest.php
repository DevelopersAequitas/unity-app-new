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

    public function test_circle_cadence_and_resource_convert_local_times_to_utc(): void
    {
        $rawRepeat = [
            'repeat_every' => 1,
            'frequency' => 'monthly',
            'week_of_month' => 'week_2',
            'weekday' => 'friday',
            'start_time' => '18:30',
            'end_time' => '21:00',
        ];

        $normalized = Circle::normalizeCadencePayload($rawRepeat);

        $this->assertSame('13:00', $normalized['start_time']);
        $this->assertSame('13:00', $normalized['time']);
        $this->assertSame('13:00', $normalized['default_meet_time']);
        $this->assertSame('15:30', $normalized['end_time']);
        $this->assertSame('15:30', $normalized['default_meet_end_time']);

        $this->assertSame('13:00', $normalized['start_time_utc']);
        $this->assertSame('15:30', $normalized['end_time_utc']);
        $this->assertSame('18:30', $normalized['start_time_local']);
        $this->assertSame('21:00', $normalized['end_time_local']);

        $circle = new Circle([
            'name' => 'Ahmedabad Events Circle',
            'slug' => 'ahmedabad-events-circle',
            'status' => 'active',
            'type' => 'public',
            'calendar' => [
                'timezone' => 'Asia/Kolkata',
                'settings' => [
                    'meeting_repeat' => $rawRepeat,
                ],
                'meeting_schedule' => [$rawRepeat],
            ],
            'meeting_repeat' => $rawRepeat,
        ]);
        $circle->id = 'circle-utc-test-1';
        $circle->setRelation('members', collect());

        $resourceArray = (new CircleResource($circle))->toArray(Request::create('/api/v1/circles'));

        $this->assertSame('13:00', data_get($resourceArray, 'meeting_repeat.start_time'));
        $this->assertSame('13:00', data_get($resourceArray, 'meeting_repeat.time'));
        $this->assertSame('13:00', data_get($resourceArray, 'meeting_repeat.default_meet_time'));
        $this->assertSame('15:30', data_get($resourceArray, 'meeting_repeat.end_time'));
        $this->assertSame('15:30', data_get($resourceArray, 'meeting_repeat.default_meet_end_time'));

        $this->assertSame('13:00', data_get($resourceArray, 'calendar.start_time'));
        $this->assertSame('15:30', data_get($resourceArray, 'calendar.end_time'));
        $this->assertSame('13:00', data_get($resourceArray, 'calendar.default_meet_time'));

        $this->assertSame('13:00', data_get($resourceArray, 'upcoming_meeting.time'));
        $this->assertSame('13:00', data_get($resourceArray, 'upcoming_meeting.start_time'));
        $this->assertSame('15:30', data_get($resourceArray, 'upcoming_meeting.end_time'));
        $this->assertSame('18:30', data_get($resourceArray, 'upcoming_meeting.start_time_local'));
        $this->assertSame('21:00', data_get($resourceArray, 'upcoming_meeting.end_time_local'));
    }
}
