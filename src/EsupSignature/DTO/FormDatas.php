<?php

namespace OpenDemat\Core\EsupSignature\DTO;

readonly class FormDatas
{
    /** @param array<string, string> $data */
    public function __construct(
        public array $data,
    ) {
    }

    public function toJson(): string
    {
        return json_encode($this->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }
}
