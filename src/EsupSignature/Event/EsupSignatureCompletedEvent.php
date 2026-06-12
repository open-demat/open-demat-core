<?php

namespace OpenDemat\Core\EsupSignature\Event;

use OpenDemat\Core\EsupSignature\DTO\SignRequestStatus;
use OpenDemat\Core\EsupSignature\Entity\SignRequest;

final class EsupSignatureCompletedEvent
{
    public function __construct(
        public readonly SignRequest       $signRequest,
        public readonly SignRequestStatus $status,
        public readonly array             $payload,
    ) {
    }
}
