<?php

namespace OpenDemat\Core\Controller;

use OpenDemat\Core\Service\StaticDocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StaticDocumentController extends AbstractController
{
    #[Route('/documents/static/{code}', name: 'open_demat_static_document', requirements: ['code' => '[A-Za-z0-9_.-]+'], methods: ['GET'])]
    public function show(string $code, StaticDocumentService $staticDocuments): StreamedResponse
    {
        return $staticDocuments->streamByCode($code, 'inline');
    }
}
