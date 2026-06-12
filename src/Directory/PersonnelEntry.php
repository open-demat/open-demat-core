<?php

namespace OpenDemat\Core\Directory;

final readonly class PersonnelEntry
{
    public function __construct(
        public string $id,
        public string $displayName,
        public ?string $lastName = null,
        public ?string $firstName = null,
        public ?string $email = null,
        public ?string $username = null,
        public ?string $title = null,
        public ?string $service = null,
        public ?string $structure = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->displayName,
            'lastName' => $this->lastName,
            'firstName' => $this->firstName,
            'email' => $this->email,
            'username' => $this->username,
            'title' => $this->title,
            'service' => $this->service,
            'structure' => $this->structure,
        ];
    }
}
