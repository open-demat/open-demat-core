<?php

namespace OpenDemat\Core\EsupSignature\DTO;

readonly class Recipient
{
    public function __construct(
        public string  $email,
        public int     $step       = 0,
        public ?string $name       = null,
        public ?string $firstName  = null,
        public ?string $phone      = null,
        public bool    $forceSms   = false,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'step'      => $this->step,
            'email'     => $this->email,
            'name'      => $this->name,
            'firstName' => $this->firstName,
            'phone'     => $this->phone,
            'forceSms'  => $this->forceSms ? 'true' : 'false',
        ], fn($v) => $v !== null);
    }
}
