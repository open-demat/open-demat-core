<?php

namespace OpenDemat\Core\EsupSignature\DTO;

readonly class SignRequestOptions
{
    public function __construct(
        public ?string $title        = null,
        public ?string $comment      = null,
        public bool    $pending      = true,
        public bool    $sendEmail    = true,
        public array   $targetEmails = [],
        public array   $ccEmails     = [],
        public array   $targetUrls   = [],
        public ?string $targetUrl    = null,
        public ?string $caseType     = null,
        public ?string $caseId       = null,
    ) {
    }
}
