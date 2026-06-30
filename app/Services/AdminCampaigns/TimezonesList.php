<?php

namespace App\Services\AdminCampaigns;

class TimezonesList
{
    public static function all(): array
    {
        return [
            'UTC' => 'UTC (GMT+00:00)',
            'Asia/Kolkata' => 'Asia/Kolkata (IST - GMT+05:30)',
            'Europe/London' => 'Europe/London (GMT+00:00 / +01:00 DST)',
            'America/New_York' => 'America/New_York (EST/EDT - GMT-05:00 / -04:00)',
            'America/Chicago' => 'America/Chicago (CST/CDT - GMT-06:00 / -05:00)',
            'America/Denver' => 'America/Denver (MST/MDT - GMT-07:00 / -06:00)',
            'America/Los_Angeles' => 'America/Los_Angeles (PST/PDT - GMT-08:00 / -07:00)',
            'Asia/Dubai' => 'Asia/Dubai (GST - GMT+04:00)',
            'Asia/Singapore' => 'Asia/Singapore (SGT - GMT+08:00)',
            'Australia/Sydney' => 'Australia/Sydney (AEST/AEDT - GMT+10:00 / +11:00)',
        ];
    }
}
