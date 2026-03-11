<?php

namespace App\Controller;

use App\Http\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class HealthController extends AbstractController
{
    #[OA\Tag(name: 'Health')]
    #[OA\Get(
        path: '/api/health',
        summary: 'API health check',
        security: [],
        responses: [new OA\Response(response: 200, description: 'API is healthy')]
    )]
    public function health(): JsonResponse
    {
        return ApiResponse::success(
            data: ['version' => '1.0.0'],
            message: 'API is healthy'
        );
    }
}
