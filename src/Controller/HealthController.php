<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class HealthController extends AbstractController
{
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'version' => '1.0.0',
        ]);
    }
}
