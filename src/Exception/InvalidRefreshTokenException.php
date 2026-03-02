<?php

namespace App\Exception;

final class InvalidRefreshTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Invalid or expired refresh token.');
    }
}
