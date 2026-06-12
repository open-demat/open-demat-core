<?php

namespace OpenDemat\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'role')]
#[ORM\UniqueConstraint(name: 'uniq_role_code', columns: ['code'])]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190)]
    private string $code;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $moduleKey = null;

    #[ORM\Column(type: Types::JSON)]
    private array $children = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

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

    public function getModuleKey(): ?string
    {
        return $this->moduleKey;
    }

    public function setModuleKey(?string $moduleKey): self
    {
        $this->moduleKey = $moduleKey;
        return $this;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): self
    {
        $this->children = array_values($children);
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
}