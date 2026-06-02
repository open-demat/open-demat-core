<?php

namespace OpenDemat\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'static_document')]
#[ORM\UniqueConstraint(name: 'uniq_static_document_code', columns: ['code'])]
#[ORM\Index(columns: ['scope'], name: 'idx_static_document_scope')]
#[ORM\Index(columns: ['active'], name: 'idx_static_document_active')]
class StaticDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $code;

    #[ORM\Column(type: 'string', length: 80)]
    private string $scope;

    #[ORM\Column(type: 'string', length: 180)]
    private string $label;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Document $document;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'string', length: 20)]
    private string $disposition = 'inline';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        string $code,
        string $scope,
        string $label,
        Document $document,
        string $disposition = 'inline',
    ) {
        $this->code = $code;
        $this->scope = $scope;
        $this->label = $label;
        $this->document = $document;
        $this->setDisposition($disposition);
        $this->createdAt = new \DateTimeImmutable();
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

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): self
    {
        $this->document = $document;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getDisposition(): string
    {
        return $this->disposition;
    }

    public function setDisposition(string $disposition): self
    {
        $this->disposition = in_array($disposition, ['inline', 'attachment'], true)
            ? $disposition
            : 'inline';

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
