<?php

namespace OpenDemat\Core\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use OpenDemat\Core\Entity\Document;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Service\MinioStorage;

/**
 * Proxy Symfony → Garage/S3.
 *
 * Garage n'est pas exposé publiquement. Ce contrôleur récupère le fichier
 * depuis S3 et le stream au navigateur avec les bons en-têtes.
 * Réservé aux documents du déposant ou aux administrateurs. Les bundles métier
 * doivent exposer leurs propres routes avec leurs règles d’autorisation.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DocumentController extends AbstractController
{
    public function __construct(
        private readonly MinioStorage $storage,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/documents/{key}', name: 'document_proxy', requirements: ['key' => '.+'], methods: ['GET'])]
    public function proxy(string $key): Response
    {
        if (!preg_match('#^documents/([0-9a-f-]{36})$#i', $key, $matches) || !Uuid::isValid($matches[1])) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        $document = $this->em->find(Document::class, Uuid::fromString($matches[1]));
        if (!$document instanceof Document) {
            throw $this->createNotFoundException('Document introuvable.');
        }
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null ||
            (!$this->isGranted('ROLE_ADMIN') && $document->getUploadedBy()?->getId() !== $user->getId())) {
            throw $this->createAccessDeniedException();
        }

        [$content, $contentType] = $this->storage->getObject($key);
        $fileName = $document->getOriginalName();
        $contentType = $document->getMimeType();

        $response = new StreamedResponse(function () use ($content) {
            echo $content;
        });

        $response->headers->set('Content-Type', $contentType ?: 'application/octet-stream');
        $response->headers->set('Content-Length', (string) strlen($content));
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition('attachment', $fileName, 'document')
        );
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
