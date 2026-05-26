<?php

/**
 * Open Demat Core – Abstract Process Entity
 *
 * Cette classe abstraite fournit une base commune pour les entités
 * représentant des processus métier dans la plateforme Core Open Demat.
 * Elle définit les propriétés et comportements standards utilisés
 * par les objets métiers pilotés par un workflow.
 *
 * Chaque entité héritant de cette classe dispose d’un identifiant,
 * de dates de création et de mise à jour ainsi que d’un statut
 * représentant l’état courant du processus dans le workflow.
 *
 * Cette classe permet d’uniformiser la gestion des entités de
 * processus dans les différents modules applicatifs et d’assurer
 * une cohérence dans le suivi des états et des métadonnées.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Process;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractProcessEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $updatedAt;

    /**
     * Statut principal du process (workflow marking).
     * Exemple: "enregistre", "EN_MODERATION_SHS", ...
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    protected string $statut = 'DRAFT';

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt ??= $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    protected function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }
}
