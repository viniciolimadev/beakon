<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    // ── GET /api/health ───────────────────────────────────────

    public function test_health_returns_200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
    }

    public function test_health_returns_json_content_type(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function test_health_response_has_expected_structure(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $body = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('message', $body);
        $this->assertSame('API is healthy', $body['message']);
        $this->assertSame('1.0.0', $body['data']['version']);
    }
}
