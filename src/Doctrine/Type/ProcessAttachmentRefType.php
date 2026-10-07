<?php

namespace OpenDemat\Core\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use OpenDemat\Core\ValueObject\ProcessAttachmentRef;

class ProcessAttachmentRefType extends Type
{
    public const NAME = 'process_attachment_ref';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ProcessAttachmentRef
    {
        if ($value === null) {
            return null;
        }

        return new ProcessAttachmentRef((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ProcessAttachmentRef) {
            return $value->id;
        }

        return (int) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
