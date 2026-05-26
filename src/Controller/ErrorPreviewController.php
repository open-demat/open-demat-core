<?php

namespace OpenDemat\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ErrorPreviewController extends AbstractController
{
    #[Route('/_preview/error/{code}', name: 'app_preview_error')]
    public function preview(int $code): Response
    {
        return match ($code) {
            403 => new Response(
                $this->renderView('bundles/TwigBundle/Exception/error403.html.twig', [
                    'status_code' => 403,
                    'status_text' => 'Forbidden',
                ]),
                403
            ),
            404 => new Response(
                $this->renderView('bundles/TwigBundle/Exception/error404.html.twig', [
                    'status_code' => 404,
                    'status_text' => 'Not Found',
                ]),
                404
            ),
            500 => new Response(
                $this->renderView('bundles/TwigBundle/Exception/error500.html.twig', [
                    'status_code' => 500,
                    'status_text' => 'Internal Server Error',
                ]),
                500
            ),
            default => new Response(
                $this->renderView('bundles/TwigBundle/Exception/error.html.twig', [
                    'status_code' => $code,
                    'status_text' => 'Erreur',
                ]),
                $code
            ),
        };
    }
}