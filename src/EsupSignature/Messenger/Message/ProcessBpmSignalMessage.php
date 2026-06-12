<?php

namespace OpenDemat\Core\EsupSignature\Messenger\Message;

final class ProcessBpmSignalMessage
{
    public function __construct(
        public readonly array $payload,
    ) {
    }
}
