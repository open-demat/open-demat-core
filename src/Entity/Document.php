<?php

/**
 * Open Demat Core – Document Entity
 *
 * Cette entité représente un fichier stocké dans le système documentaire
 * du Core Open Demat. Elle contient les métadonnées associées à un fichier
 * téléversé dans la plateforme, telles que son nom original, son type MIME,
 * sa taille, son empreinte de contrôle et l’utilisateur ayant effectué
 * le dépôt.
 *
 * Les fichiers eux-mêmes sont stockés dans un système de stockage externe
 * (via Flysystem ou un service compatible objet), tandis que cette entité
 * permet d’enregistrer et de référencer leurs informations dans la base
 * de données.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: \OpenDemat\Core\Repository\DocumentRepository::class)]
#[ORM\Table(name: 'document')]
class Document
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(name: 'original_name', type: 'string', length: 255)]
    private string $originalName;

    #[ORM\Column(name: 'mime_type', type: 'string', length: 128)]
    private string $mimeType;

    #[ORM\Column(name: 'size_bytes', type: 'bigint')]
    private int $sizeBytes;

    #[ORM\Column(name: 'checksum_sha256', type: 'string', length: 64, nullable: true)]
    private ?string $checksumSha256 = null;

    #[ORM\Column(name: 'bucket', type: 'string', length: 100)]
    private string $bucket;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploaded_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    public function __construct(
        string $originalName,
        string $mimeType,
        int $sizeBytes,
        string $bucket,
        ?string $checksumSha256 = null,
        ?User $uploadedBy = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->id = Uuid::v7();
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->bucket = $bucket;
        $this->checksumSha256 = $checksumSha256;
        $this->uploadedBy = $uploadedBy;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function getChecksumSha256(): ?string { return $this->checksumSha256; }
    public function getBucket(): string { return $this->bucket; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;
        return $this;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function setSizeBytes(int $sizeBytes): self
    {
        $this->sizeBytes = $sizeBytes;
        return $this;
    }

    public function setChecksumSha256(?string $checksumSha256): self
    {
        $this->checksumSha256 = $checksumSha256;
        return $this;
    }

    public function setUploadedBy(?User $uploadedBy): self
    {
        $this->uploadedBy = $uploadedBy;
        return $this;
    }
}
