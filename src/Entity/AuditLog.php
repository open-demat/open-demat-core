<?php

/**
 * Open Demat Core – Audit Log Entity
 *
 * Cette entité représente une entrée du journal d’audit du Core Open Demat.
 * Elle permet d’enregistrer les actions réalisées dans les différentes
 * applications de la plateforme afin d’assurer la traçabilité des
 * opérations métier et techniques.
 *
 * Chaque entrée d’audit contient des informations sur le processus
 * concerné, l’action effectuée, l’utilisateur à l’origine de l’action,
 * l’entité éventuellement impactée, ainsi que des informations de
 * contexte telles que l’adresse IP, la route Symfony, le user-agent
 * et les transitions d’état.
 *
 * Ce mécanisme permet de suivre l’historique des actions utilisateurs,
 * de faciliter le débogage et de répondre aux exigences de traçabilité
 * des applications internes.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OpenDemat\Core\Repository\AuditLogRepository;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_log_created_at')]
#[ORM\Index(columns: ['process_name'], name: 'idx_audit_log_process_name')]
#[ORM\Index(columns: ['action'], name: 'idx_audit_log_action')]
#[ORM\Index(columns: ['user_identifier'], name: 'idx_audit_log_user_identifier')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_audit_log_entity')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $processName;

    #[ORM\Column(length: 150)]
    private string $action;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $userIdentifier = null;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $userDisplayName = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityLabel = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $oldState = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $newState = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $context = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcessName(): string
    {
        return $this->processName;
    }

    public function setProcessName(string $processName): self
    {
        $this->processName = $processName;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier;

        return $this;
    }

    public function getUserDisplayName(): ?string
    {
        return $this->userDisplayName;
    }

    public function setUserDisplayName(?string $userDisplayName): self
    {
        $this->userDisplayName = $userDisplayName;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function setEntityLabel(?string $entityLabel): self
    {
        $this->entityLabel = $entityLabel;

        return $this;
    }

    public function getOldState(): ?string
    {
        return $this->oldState;
    }

    public function setOldState(?string $oldState): self
    {
        $this->oldState = $oldState;

        return $this;
    }

    public function getNewState(): ?string
    {
        return $this->newState;
    }

    public function setNewState(?string $newState): self
    {
        $this->newState = $newState;

        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}