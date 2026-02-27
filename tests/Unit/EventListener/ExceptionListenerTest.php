<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\ExceptionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ExceptionListenerTest extends TestCase
{
    private ExceptionListener $listener;
    private HttpKernelInterface $kernel;
    private Request $request;

    protected function setUp(): void
    {
        $this->listener = new ExceptionListener();
        $this->kernel   = $this->createStub(HttpKernelInterface::class);
        $this->request  = Request::create('/');
    }

    private function makeEvent(\Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->kernel,
            $this->request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );
    }

    // ── HTTP exceptions ───────────────────────────────────────

    #[Test]
    public function http_exception_is_converted_to_api_error_format(): void
    {
        $event = $this->makeEvent(new NotFoundHttpException('User not found'));

        ($this->listener)($event);

        $response = $event->getResponse();
        $this->assertSame(404, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('User not found', $body['error']);
        $this->assertSame(404, $body['code']);
    }

    #[Test]
    #[DataProvider('httpExceptionProvider')]
    public function maps_http_exceptions_to_correct_status(
        \Throwable $exception,
        int $expectedStatus
    ): void {
        $event = $this->makeEvent($exception);
        ($this->listener)($event);

        $this->assertSame($expectedStatus, $event->getResponse()->getStatusCode());
    }

    public static function httpExceptionProvider(): array
    {
        return [
            '404' => [new NotFoundHttpException(), 404],
            '403' => [new AccessDeniedHttpException(), 403],
            '401' => [new UnauthorizedHttpException('Bearer'), 401],
        ];
    }

    // ── exception genérica (500) ──────────────────────────────

    #[Test]
    public function generic_exception_returns_500(): void
    {
        $event = $this->makeEvent(new \RuntimeException('Unexpected error'));

        ($this->listener)($event);

        $response = $event->getResponse();
        $this->assertSame(500, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('Internal Server Error', $body['error']);
        $this->assertSame(500, $body['code']);
    }

    // ── mensagens padrão ──────────────────────────────────────

    #[Test]
    public function http_exception_without_message_uses_default(): void
    {
        $event = $this->makeEvent(new NotFoundHttpException());

        ($this->listener)($event);

        $body = json_decode($event->getResponse()->getContent(), true);
        $this->assertSame('Not Found', $body['error']);
    }
}
