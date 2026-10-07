<?php

namespace OpenDemat\Core\ValueObject;

use OpenDemat\Core\Entity\ProcessAttachment;

/**
 * Référence douce vers un ProcessAttachment (soft reference — pas de FK Doctrine).
 * Utiliser à la place de ?int pour typer sémantiquement les champs d'attachement dans les bundles.
 */
final readonly class ProcessAttachmentRef
{
    public function __construct(public int $id) {}

    public static function fromAttachment(ProcessAttachment $attachment): self
    {
        return new self($attachment->getId());
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }
}
