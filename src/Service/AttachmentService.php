<?php

/**
 * Open Demat Core – Attachment Service
 *
 * Ce service centralise la gestion des pièces jointes associées
 * aux dossiers métier de la plateforme Core Open Demat.
 *
 * Il permet de téléverser des fichiers, d’attacher des documents
 * existants à un dossier, de remplacer le contenu d’un document,
 * de supprimer une pièce jointe et d’ouvrir un flux de lecture
 * sur un fichier stocké.
 *
 * Les fichiers sont stockés dans le système documentaire via
 * l’entité `Document`, puis liés aux dossiers métier à l’aide
 * de l’entité `ProcessAttachment`. Le service prend également
 * en compte les documents personnels des utilisateurs (`UserDocument`)
 * afin d’éviter les suppressions de fichiers encore utilisés
 * ailleurs dans l’application.
 *
 * Ce mécanisme permet de mutualiser la gestion documentaire
 * pour l’ensemble des processus métier (CSST, remboursements,
 * courriers, etc.) de manière sécurisée et générique.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use OpenDemat\Core\Entity\Document;
use OpenDemat\Core\Entity\ProcessAttachment;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Repository\ProcessAttachmentRepository;
use OpenDemat\Core\Repository\UserDocumentRepository;

class AttachmentService
{
    public function __construct(
            private readonly EntityManagerInterface $em,
            private readonly FilesystemOperator $documentsStorage,
            private readonly ProcessAttachmentRepository $repo,
            private readonly UserDocumentRepository $userDocRepo,
            private readonly string $minioBucket,
    ) {}

    /**
     * @param UploadedFile[] $files
     * @return ProcessAttachment[]
     */
    public function uploadForCase(
        string $processName,
        string $caseType,
        int $caseId,
        array $files,
        ?User $uploadedBy = null,
        int $maxFiles = 10,
    ): array {
        $existing = $this->repo->countForCase($processName, $caseType, $caseId);
        if ($existing + count($files) > $maxFiles) {
            throw new \RuntimeException(sprintf(
                'Maximum %d pièces jointes (déjà %d, ajout %d).',
                $maxFiles,
                $existing,
                count($files)
            ));
        }

        $created = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $doc = new Document(
                originalName: $file->getClientOriginalName(),
                mimeType: $file->getClientMimeType() ?: 'application/octet-stream',
                sizeBytes: (int) $file->getSize(),
                bucket: $this->minioBucket, // ✅ FIX
                checksumSha256: hash_file('sha256', $file->getPathname()),
                uploadedBy: $uploadedBy,
            );

            // Convention simple : un seul bucket configuré via Flysystem, et key = documents/{uuid}
            $key = 'documents/' . $doc->getId()->toRfc4122();

            $stream = fopen($file->getPathname(), 'rb');
            $this->documentsStorage->writeStream($key, $stream, ['ContentType' => $doc->getMimeType()]);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $pa = new ProcessAttachment($processName, $caseType, $caseId, $doc, $uploadedBy);

            $this->em->persist($doc);
            $this->em->persist($pa);
            $created[] = $pa;
        }

        $this->em->flush();

        return $created;
    }

    public function replaceAttachmentFile(
        ProcessAttachment $attachment,
        UploadedFile $newFile,
        ?User $uploadedBy = null,
        bool $keepOriginalName = false,
    ): Document {
        return $this->replaceDocumentFile(
            $attachment->getDocument(),
            $newFile,
            $uploadedBy,
            $keepOriginalName
        );
    }

    public function deleteProcessAttachment(ProcessAttachment $attachment): void
    {
        $doc = $attachment->getDocument();
        $key = 'documents/' . $doc->getId()->toRfc4122();

        // 1) supprimer la ligne ProcessAttachment
        $this->em->remove($attachment);
        $this->em->flush();

        // 2) si le doc est dans UserDocument => on garde
        if ($this->userDocRepo->existsByDocument($doc)) {
            return;
        }

        // 3) si le doc est encore attaché ailleurs => on garde (important)
        if ($this->repo->countByDocument($doc) > 0) {
            return;
        }

        // 4) sinon : supprimer le fichier + la ligne Document
        try {
            if ($this->documentsStorage->fileExists($key)) {
                $this->documentsStorage->delete($key);
            }
        } catch (\Throwable $e) {
            // optionnel: logger warning
        }

        $this->em->remove($doc);
        $this->em->flush();
    }


    /**
     * Accroche des Documents déjà existants (ex: user-vault) à un dossier (case),
     * sans ré-uploader de fichier. Crée seulement des ProcessAttachment.
     *
     * @param int[] $userDocumentIds  IDs de UserDocument (pas Document)
     * @return ProcessAttachment[]
     */
    public function attachExistingUserDocumentsForCase(
        string $processName,
        string $caseType,
        int $caseId,
        array $userDocumentIds,
        User $requester,
        int $maxFiles = 10,
    ): array {
        $userDocumentIds = array_values(array_unique(array_filter(array_map('intval', $userDocumentIds))));
        if ($userDocumentIds === []) {
            return [];
        }

        // Max files (existing + ajout)
        $existingCount = $this->repo->countForCase($processName, $caseType, $caseId);
        if ($existingCount + count($userDocumentIds) > $maxFiles) {
            throw new \RuntimeException(sprintf(
                'Maximum %d pièces jointes (déjà %d, ajout %d).',
                $maxFiles,
                $existingCount,
                count($userDocumentIds)
            ));
        }

        // On charge les UserDocument demandés
        /** @var \Doctrine\Persistence\ObjectRepository<\OpenDemat\Core\Entity\UserDocument> $udRepo */
        $udRepo = $this->em->getRepository(\OpenDemat\Core\Entity\UserDocument::class);

        $created = [];

        foreach ($userDocumentIds as $udId) {
            /** @var \OpenDemat\Core\Entity\UserDocument|null $ud */
            $ud = $udRepo->find($udId);
            if (!$ud) {
                continue;
            }

            // ✅ sécurité : le doc doit appartenir au demandeur
            if ($ud->getUser()->getId() !== $requester->getId()) {
                continue; // ou throw AccessDenied, mais en batch c'est chiant
            }

            $doc = $ud->getDocument();

            // ✅ éviter doublon : si ce Document est déjà attaché au case
            if ($this->repo->existsForCaseAndDocument($processName, $caseType, $caseId, $doc)) {
                continue;
            }

            $pa = new ProcessAttachment($processName, $caseType, $caseId, $doc, $requester);
            $this->em->persist($pa);
            $created[] = $pa;
        }

        $this->em->flush();

        return $created;
    }


    /**
     * Remplace le contenu du Document existant (même UUID => même key MinIO),
     * et met à jour ses métadonnées.
     *
     * Ne touche PAS aux champs ProcessAttachment (process/type/caseId).
     */
    private function replaceDocumentFile(
        Document $document,
        UploadedFile $newFile,
        ?User $uploadedBy = null,
        bool $keepOriginalName = false,
    ): Document {
        if (!$newFile->isValid()) {
            throw new \RuntimeException('Fichier upload invalide.');
        }

        $key = 'documents/' . $document->getId()->toRfc4122();

        // 1) Upload / overwrite dans MinIO
        $stream = fopen($newFile->getPathname(), 'rb');
        try {
            $mime = $newFile->getClientMimeType() ?: 'application/octet-stream';

            $this->documentsStorage->writeStream($key, $stream, [
                'ContentType' => $mime,
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // 2) Update métadonnées Document
        $document->setMimeType($newFile->getClientMimeType() ?: 'application/octet-stream');
        $document->setSizeBytes((int) $newFile->getSize());
        $document->setChecksumSha256(hash_file('sha256', $newFile->getPathname()));
        $document->setUploadedBy($uploadedBy);

        if (!$keepOriginalName) {
            $document->setOriginalName($newFile->getClientOriginalName());
        }

        $this->em->flush();

        return $document;
    }

    /** @return ProcessAttachment[] */
    public function listForCase(string $processName, string $caseType, int $caseId): array
    {
        return $this->repo->findForCase($processName, $caseType, $caseId);
    }

    /** @return resource */
    public function openStream(Document $document)
    {
        $key = 'documents/' . $document->getId()->toRfc4122();
        return $this->documentsStorage->readStream($key);
    }
}
