<?php

namespace App\Controller;

use App\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class HealthController extends AbstractController
{
    public function health(): JsonResponse
    {
        return ApiResponse::success(
            data: ['version' => '1.0.0'],
            message: 'API is healthy'
        );
    }
}
