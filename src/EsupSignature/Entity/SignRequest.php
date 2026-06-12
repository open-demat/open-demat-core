<?php

namespace OpenDemat\Core\EsupSignature\Entity;

use Doctrine\ORM\Mapping as ORM;
use OpenDemat\Core\EsupSignature\DTO\SignRequestStatus;

#[ORM\Entity]
#[ORM\Table(name: 'sign_request', schema: 'esup_signature')]
#[ORM\HasLifecycleCallbacks]
class SignRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $esupId;

    /** Type d'objet ES : signrequest | workflow | form */
    #[ORM\Column(length: 32)]
    private string $esupType;

    /** CaseType BPM Open Demat (ex: FEBRH, POINTSDEPARCOURS). */
    #[ORM\Column(length: 64)]
    private string $caseType;

    #[ORM\Column(length: 255)]
    private string $caseId;

    #[ORM\Column(length: 255)]
    private string $createByEppn;

    #[ORM\Column(length: 32, enumType: SignRequestStatus::class)]
    private SignRequestStatus $status = SignRequestStatus::Pending;

    /** Identifiant du serveur ES source (ex: "tst", "local") — null si serveur unique. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $serverId = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $signedFileKey = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $callbackPayload = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $esupId,
        string $esupType,
        string $caseType,
        string $caseId,
        string $createByEppn,
    ) {
        $this->esupId       = $esupId;
        $this->esupType     = $esupType;
        $this->caseType     = $caseType;
        $this->caseId       = $caseId;
        $this->createByEppn = $createByEppn;
        $this->createdAt    = new \DateTimeImmutable();
        $this->updatedAt    = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int                   { return $this->id; }
    public function getEsupId(): string             { return $this->esupId; }
    public function getEsupType(): string           { return $this->esupType; }
    public function getCaseType(): string           { return $this->caseType; }
    public function getCaseId(): string             { return $this->caseId; }
    public function getCreateByEppn(): string       { return $this->createByEppn; }
    public function getStatus(): SignRequestStatus  { return $this->status; }
    public function getSignedFileKey(): ?string     { return $this->signedFileKey; }
    public function getCallbackPayload(): ?array    { return $this->callbackPayload; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function setStatus(SignRequestStatus $status): void
    {
        $this->status    = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getServerId(): ?string     { return $this->serverId; }

    public function setServerId(?string $serverId): void
    {
        $this->serverId = $serverId;
    }

    public function setSignedFileKey(string $key): void
    {
        $this->signedFileKey = $key;
    }

    public function setCallbackPayload(array $payload): void
    {
        $this->callbackPayload = $payload;
        $this->updatedAt       = new \DateTimeImmutable();
    }
}
