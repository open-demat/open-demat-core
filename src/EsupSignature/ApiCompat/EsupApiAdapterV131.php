<?php

namespace OpenDemat\Core\EsupSignature\ApiCompat;

use OpenDemat\Core\EsupSignature\DTO\SignType;

/**
 * Adapter pour ESUP Signature <= 1.31.x.
 * Comportement de référence : pass-through sur les SignTypes, aucune feature étendue.
 */
final class EsupApiAdapterV131 implements EsupApiAdapterInterface
{
    public function normalizeSignType(SignType $type): string
    {
        return $type->value;
    }

    public function supportsFeature(EsupFeature $feature): bool
    {
        return false;
    }

    public function getVersionLabel(): string
    {
        return '1.31';
    }
}
