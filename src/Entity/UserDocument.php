<?php

/**
 * Open Demat Core – UserDocument Entity
 *
 * Cette entité représente un document personnel appartenant à un
 * utilisateur de la plateforme Core Open Demat. Elle établit un lien entre
 * un utilisateur et un fichier stocké dans le système documentaire
 * (entité Document).
 *
 * Elle permet de constituer un espace de stockage personnel
 * ("vault") pour chaque utilisateur, dans lequel peuvent être
 * déposés différents types de documents tels que des justificatifs,
 * des pièces d’identité ou des documents administratifs.
 *
 * Des métadonnées optionnelles permettent de catégoriser les
 * documents, de leur attribuer un libellé lisible et de gérer
 * leur affichage dans l’interface utilisateur.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \OpenDemat\Core\Repository\UserDocumentRepository::class)]
#[ORM\Table(name: 'user_document')]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_document_user')]
#[ORM\Index(columns: ['document_id'], name: 'idx_user_document_document')]
#[ORM\UniqueConstraint(name: 'uniq_user_document_user_doc', columns: ['user_id', 'document_id'])]
class UserDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Propriétaire du “vault”
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    // Fichier stocké (S3/Flysystem via Document)
    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Document $document;

    // Libellé “humain” optionnel (ex: "RIB", "CNI recto", "Justificatif de domicile")
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    // Catégorie optionnelle (ex: "identite", "banque", "domicile", "autre")
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $category = null;

    // Si tu veux “épingler” des docs en haut de liste
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPinned = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // Qui a ajouté le doc au vault (souvent = user, mais utile si admin)
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    public function __construct(
        User $user,
        Document $document,
        ?string $label = null,
        ?string $category = null,
        bool $isPinned = false,
        ?User $createdBy = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->user = $user;
        $this->document = $document;
        $this->label = $label;
        $this->category = $category;
        $this->isPinned = $isPinned;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function getDocument(): Document { return $this->document; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): self { $this->label = $label; return $this; }

    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): self { $this->category = $category; return $this; }

    public function isPinned(): bool { return $this->isPinned; }
    public function setIsPinned(bool $isPinned): self { $this->isPinned = $isPinned; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }
}
