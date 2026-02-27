<?php

namespace App\Tests\Unit\Http;

use App\Http\ApiResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    // ── success ───────────────────────────────────────────────

    #[Test]
    public function success_returns_200_with_data_and_message(): void
    {
        $response = ApiResponse::success(['id' => 1], 'OK');

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(['id' => 1], $body['data']);
        $this->assertSame('OK', $body['message']);
    }

    #[Test]
    public function success_defaults_to_empty_message_and_null_data(): void
    {
        $response = ApiResponse::success();

        $body = json_decode($response->getContent(), true);
        $this->assertNull($body['data']);
        $this->assertSame('', $body['message']);
    }

    // ── created ───────────────────────────────────────────────

    #[Test]
    public function created_returns_201(): void
    {
        $response = ApiResponse::created(['id' => 'uuid-1'], 'Created');

        $this->assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame(['id' => 'uuid-1'], $body['data']);
        $this->assertSame('Created', $body['message']);
    }

    // ── noContent ─────────────────────────────────────────────

    #[Test]
    public function no_content_returns_204(): void
    {
        $response = ApiResponse::noContent();

        $this->assertSame(204, $response->getStatusCode());
        // JsonResponse(null) serializa como {} — sem campos de dados
        $body = json_decode($response->getContent(), true);
        $this->assertEmpty($body);
    }

    // ── error ─────────────────────────────────────────────────

    #[Test]
    public function error_returns_given_status_with_error_and_code(): void
    {
        $response = ApiResponse::error('Something went wrong', 400);

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('Something went wrong', $body['error']);
        $this->assertSame(400, $body['code']);
    }

    // ── helpers de erro ───────────────────────────────────────

    #[Test]
    #[DataProvider('errorHelperProvider')]
    public function error_helpers_return_correct_status(string $method, int $expectedStatus): void
    {
        $response = ApiResponse::{$method}();

        $this->assertSame($expectedStatus, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame($expectedStatus, $body['code']);
        $this->assertArrayHasKey('error', $body);
    }

    public static function errorHelperProvider(): array
    {
        return [
            'notFound'      => ['notFound', 404],
            'unauthorized'  => ['unauthorized', 401],
            'forbidden'     => ['forbidden', 403],
            'unprocessable' => ['unprocessable', 422],
        ];
    }

    // ── content-type ──────────────────────────────────────────

    #[Test]
    public function responses_have_json_content_type(): void
    {
        foreach ([ApiResponse::success(), ApiResponse::error('err', 400)] as $response) {
            $this->assertStringContainsString(
                'application/json',
                $response->headers->get('Content-Type')
            );
        }
    }
}
