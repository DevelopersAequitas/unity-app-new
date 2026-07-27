<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Notifications\NotificationService;
use Tests\TestCase;

class NotificationTemplateRenderingTest extends TestCase
{
    public function test_renders_person_and_requirement_title_placeholders(): void
    {
        $service = new NotificationService;

        $template = '<person> is looking for: "[Requirement Title]"';
        $placeholders = [
            'person' => 'Rajesh Kumar',
            'requirement_title' => 'Website Development',
        ];

        $rendered = $service->renderTemplate($template, $placeholders);

        $this->assertEquals('Rajesh Kumar is looking for: "Website Development"', $rendered);
    }

    public function test_renders_bracket_and_tag_variations(): void
    {
        $service = new NotificationService;

        $template = 'Hello <person>, check [Event Title] on <date> with [X] peers';
        $placeholders = [
            'person' => 'Aarav',
            'event_title' => 'Unity Annual Summit',
            'date' => '28 Jul 2026',
            'x' => '5',
        ];

        $rendered = $service->renderTemplate($template, $placeholders);

        $this->assertEquals('Hello Aarav, check Unity Annual Summit on 28 Jul 2026 with 5 peers', $rendered);
    }
}
