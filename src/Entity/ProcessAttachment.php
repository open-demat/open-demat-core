<?php

/**
 * Open Demat Core – Process Attachment Entity
 *
 * Cette entité permet d’associer un document à un dossier métier
 * appartenant à un processus applicatif. Elle sert de lien entre
 * le système documentaire du Core et les différents processus
 * (CSST, courriers, remboursements, etc.).
 *
 * Chaque attachement référence un document stocké dans le système
 * documentaire et l’associe à un objet métier identifié par :
 * - un nom de processus
 * - un type de dossier
 * - un identifiant de dossier
 *
 * Cette approche permet de gérer les pièces jointes de manière
 * générique pour l’ensemble des modules de la plateforme sans
 * dépendance directe entre les entités métier et les documents.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \OpenDemat\Core\Repository\ProcessAttachmentRepository::class)]
#[ORM\Table(name: 'process_attachment')]
#[ORM\Index(columns: ['process_name', 'case_type', 'case_id'], name: 'idx_pa_case')]
#[ORM\UniqueConstraint(name: 'uniq_pa_case_doc', columns: ['process_name', 'case_type', 'case_id', 'document_id'])]
class ProcessAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Exemple: "csst"
    #[ORM\Column(name: 'process_name', type: 'string', length: 50)]
    private string $processName;

    // Exemple: "csst_observation" (fortement conseillé pour éviter collisions)
    #[ORM\Column(name: 'case_type', type: 'string', length: 50)]
    private string $caseType;

    #[ORM\Column(name: 'case_id', type: 'integer')]
    private int $caseId;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Document $document;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct(string $processName, string $caseType, int $caseId, Document $document, ?User $createdBy = null)
    {
        $this->processName = $processName;
        $this->caseType = $caseType;
        $this->caseId = $caseId;
        $this->document = $document;
        $this->createdAt = new \DateTimeImmutable();
        $this->createdBy = $createdBy;
    }

    public function getId(): ?int { return $this->id; }
    public function getProcessName(): string { return $this->processName; }
    public function getCaseType(): string { return $this->caseType; }
    public function getCaseId(): int { return $this->caseId; }
    public function getDocument(): Document { return $this->document; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
}
