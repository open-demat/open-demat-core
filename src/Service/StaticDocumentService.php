<?php

namespace OpenDemat\Core\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use OpenDemat\Core\Entity\Document;
use OpenDemat\Core\Entity\StaticDocument;
use OpenDemat\Core\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StaticDocumentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FilesystemOperator $documentsStorage,
        private readonly string $minioBucket,
    ) {
    }

    public function findActiveByCode(string $code): ?StaticDocument
    {
        return $this->em->getRepository(StaticDocument::class)->findOneBy([
            'code' => $code,
            'active' => true,
        ]);
    }

    public function storeUploadedFile(
        string $code,
        string $scope,
        string $label,
        UploadedFile $file,
        ?User $uploadedBy = null,
        string $disposition = 'inline',
    ): StaticDocument {
        if (!$file->isValid()) {
            throw new \RuntimeException('Fichier upload invalide.');
        }

        $managedUploadedBy = null;
        if ($uploadedBy instanceof User && null !== $uploadedBy->getId()) {
            $managedUploadedBy = $this->em->getReference(User::class, $uploadedBy->getId());
        }

        $document = new Document(
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getClientMimeType() ?: 'application/octet-stream',
            sizeBytes: (int) $file->getSize(),
            bucket: $this->minioBucket,
            checksumSha256: hash_file('sha256', $file->getPathname()) ?: null,
            uploadedBy: $managedUploadedBy,
        );

        $key = 'documents/' . $document->getId()->toRfc4122();
        $stream = fopen($file->getPathname(), 'rb');

        try {
            $this->documentsStorage->writeStream($key, $stream, [
                'ContentType' => $document->getMimeType(),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $staticDocument = $this->em->getRepository(StaticDocument::class)->findOneBy(['code' => $code]);

        if ($staticDocument instanceof StaticDocument) {
            $staticDocument
                ->setScope($scope)
                ->setLabel($label)
                ->setDocument($document)
                ->setDisposition($disposition)
                ->setActive(true);
        } else {
            $staticDocument = new StaticDocument($code, $scope, $label, $document, $disposition);
            $this->em->persist($staticDocument);
        }

        $this->em->persist($document);
        $this->em->flush();

        return $staticDocument;
    }

    public function streamByCode(string $code, ?string $forcedDisposition = null): StreamedResponse
    {
        $staticDocument = $this->findActiveByCode($code);

        if (!$staticDocument) {
            throw new NotFoundHttpException('Document statique introuvable.');
        }

        return $this->stream($staticDocument, $forcedDisposition);
    }

    public function stream(StaticDocument $staticDocument, ?string $forcedDisposition = null): StreamedResponse
    {
        $document = $staticDocument->getDocument();
        $stream = $this->openDocumentStream($staticDocument);

        if (!is_resource($stream)) {
            throw new NotFoundHttpException('Impossible de lire le fichier.');
        }

        $response = new StreamedResponse(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        });

        $disposition = $forcedDisposition ?: $staticDocument->getDisposition();
        $filename = $document->getOriginalName() ?: 'document';
        $mimeType = $document->getMimeType() ?: 'application/octet-stream';

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                $disposition === 'attachment'
                    ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
                    : ResponseHeaderBag::DISPOSITION_INLINE,
                $filename,
                $this->asciiFallback($filename)
            )
        );
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }

    public function materializeToPath(StaticDocument $staticDocument, string $targetPath): void
    {
        $stream = $this->openDocumentStream($staticDocument);

        if (!is_resource($stream)) {
            throw new \RuntimeException('Impossible de lire le document statique.');
        }

        $directory = dirname($targetPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            fclose($stream);

            throw new \RuntimeException(sprintf('Impossible de créer le dossier %s.', $directory));
        }

        $target = fopen($targetPath, 'wb');
        if (!is_resource($target)) {
            fclose($stream);

            throw new \RuntimeException(sprintf('Impossible d écrire le fichier %s.', $targetPath));
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($target);
            fclose($stream);
        }
    }

    /** @return resource|false */
    private function openDocumentStream(StaticDocument $staticDocument)
    {
        return $this->documentsStorage->readStream(
            'documents/' . $staticDocument->getDocument()->getId()->toRfc4122()
        );
    }

    private function asciiFallback(string $name): string
    {
        $fallback = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($name));

        if ($fallback === false) {
            $fallback = $name;
        }

        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fallback) ?: 'file';
        $fallback = trim($fallback, '._-');

        return $fallback !== '' ? $fallback : 'file';
    }
}
