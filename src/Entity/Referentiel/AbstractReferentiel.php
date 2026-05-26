<?php

/**
 * Open Demat Core – Abstract Referentiel Entity
 *
 * Cette classe abstraite fournit une base commune pour les entités de
 * référentiel utilisées dans le Core Open Demat. Elle définit les propriétés
 * standard d’un référentiel applicatif telles que le code machine,
 * le libellé affiché, l’état actif/inactif ainsi que les dates de
 * création et de mise à jour.
 *
 * Elle permet d’homogénéiser la structure des référentiels dans les
 * différents modules applicatifs (ex : transporteurs, sites, services,
 * catégories, etc.) tout en centralisant la gestion des métadonnées
 * et des cycles de vie Doctrine.
 *
 * Maintenu par les contributeurs Open Demat.
 */


namespace OpenDemat\Core\Entity\Referentiel;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractReferentiel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected ?int $id = null;

    /**
     * Code stable (machine), unique par table.
     * Ex: "CHRONOPOST", "CAMPUS_CENTRE"
     */
    #[ORM\Column(type: Types::STRING, length: 120, unique: true)]
    protected string $code = '';

    /**
     * Libellé affiché (humain)
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $libelle = '';

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $actif = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?\DateTimeImmutable $updatedAt = null;

    public function __construct(?string $code = null, ?string $libelle = null)
    {
        $this->code = $code ?? '';
        $this->libelle = $libelle ?? '';

        // On initialise quand même ici (cas normal),
        // et PrePersist sécurise les cas où le ctor n’est pas appelé.
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt ??= $now;
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

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        $this->touch();
        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        $this->touch();
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        // garanti non-null après persist (PrePersist)
        return $this->createdAt ?? new \DateTimeImmutable();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        // garanti non-null après persist (PreUpdate/PrePersist)
        return $this->updatedAt ?? new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->libelle;
    }
}
