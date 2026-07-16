<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ShareRedirectTest extends TestCase
{
    /**
     * Test share redirect page renders with default config.
     */
    public function test_share_redirect_renders_with_default_peers(): void
    {
        config(['app.instance' => 'peers']);

        $response = $this->get('/share?type=post&id=123-456');

        $response->assertStatus(200);
        $response->assertSee('Open in Peers Global Unity');
        // Check HTML escaped link in anchor
        $response->assertSee('peersunity://share?type=post&amp;id=123-456', false);
        // Check raw JS variable
        $response->assertSee('var appScheme = "peersunity://share?type=post&id=123-456";', false);
    }

    /**
     * Test share redirect page renders with greenpreneur configuration.
     */
    public function test_share_redirect_renders_with_greenpreneur(): void
    {
        config(['app.instance' => 'greenpreneur']);

        $response = $this->get('/share?type=profile&id=789-101');

        $response->assertStatus(200);
        $response->assertSee('Open in Greenpreneur');
        // Check HTML escaped link in anchor
        $response->assertSee('greenpreneur://share?type=profile&amp;id=789-101', false);
        // Check raw JS variable
        $response->assertSee('var appScheme = "greenpreneur://share?type=profile&id=789-101";', false);
    }

    /**
     * Test share redirect page dynamically detects domain when app.instance is empty.
     */
    public function test_share_redirect_detects_domain_dynamically(): void
    {
        config(['app.instance' => null]);

        // Peers domain
        $response = $this->get('http://dev.peersunity.com/share?type=digital_card&id=card-123');

        $response->assertStatus(200);
        $response->assertSee('Open in Peers Global Unity');
        $response->assertSee('peersunity://share?type=digital_card&amp;id=card-123', false);
        $response->assertSee('var appScheme = "peersunity://share?type=digital_card&id=card-123";', false);

        // Greenpreneur domain
        $response = $this->get('http://dev.greenpreneur.com/share?type=digital_card&id=card-123');

        $response->assertStatus(200);
        $response->assertSee('Open in Greenpreneur');
        $response->assertSee('greenpreneur://share?type=digital_card&amp;id=card-123', false);
        $response->assertSee('var appScheme = "greenpreneur://share?type=digital_card&id=card-123";', false);
    }
}
