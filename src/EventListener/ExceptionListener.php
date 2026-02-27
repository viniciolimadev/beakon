<?php

namespace App\EventListener;

use App\Http\ApiResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof HttpExceptionInterface) {
            $status  = $throwable->getStatusCode();
            $message = $throwable->getMessage() ?: $this->defaultMessage($status);

            $event->setResponse(ApiResponse::error($message, $status));

            return;
        }

        $event->setResponse(ApiResponse::error('Internal Server Error', 500));
    }

    private function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            default => 'HTTP Error',
        };
    }
}
