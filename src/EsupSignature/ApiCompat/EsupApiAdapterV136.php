<?php

namespace OpenDemat\Core\EsupSignature\ApiCompat;

use OpenDemat\Core\EsupSignature\DTO\SignType;

/**
 * Adapter pour ESUP Signature >= 1.36.x.
 *
 * Changements par rapport à la v1.31 :
 * - PdfImageStamp, CertSign et NexuSign sont normalisés en "signature" (valeur canonique)
 * - Nouvelles features : ConvertToPDFA, SingleSignWithAnnotation, JwtAuth, AttachmentFiles
 */
final class EsupApiAdapterV136 implements EsupApiAdapterInterface
{
    public function normalizeSignType(SignType $type): string
    {
        return match($type) {
            SignType::PdfImageStamp,
            SignType::CertSign,
            SignType::NexuSign => SignType::Signature->value,
            default            => $type->value,
        };
    }

    public function supportsFeature(EsupFeature $feature): bool
    {
        return in_array($feature, [
            EsupFeature::ConvertToPDFA,
            EsupFeature::SingleSignWithAnnotation,
            EsupFeature::SignRequestParamsInStep,
            EsupFeature::AttachmentFiles,
            EsupFeature::JwtAuth,
        ], true);
    }

    public function getVersionLabel(): string
    {
        return '1.36';
    }
}
