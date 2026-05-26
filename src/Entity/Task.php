<?php

/**
 * Open Demat Core – Task Entity
 *
 * Cette entité représente une tâche à réaliser dans le cadre d’un
 * processus métier de la plateforme Open Demat. Elle permet de gérer une
 * boîte de réception de tâches pour les utilisateurs ou les rôles,
 * afin de suivre les actions à effectuer dans les différents modules
 * applicatifs (CSST, remboursements, courriers, etc.).
 *
 * Chaque tâche est associée à un dossier métier identifié par un type
 * d’objet et un identifiant, et peut être assignée soit à un utilisateur
 * spécifique, soit à un rôle applicatif.
 *
 * Ce mécanisme permet d’implémenter un système générique de gestion
 * des tâches transverses dans le Core, indépendamment des entités
 * métier spécifiques de chaque module.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'case_type', type: 'string', length: 50)]
    private string $caseType;

    #[ORM\Column(name: 'case_id', type: 'integer')]
    private int $caseId;

    // Nom du process / module (csst, remboursement, fa_fc, etc.)
    #[ORM\Column(
        name: 'process_name',
        type: 'string',
        length: 100
    )]
    private string $processName;

    #[ORM\Column(
    name: 'summary',
    type: 'string',
    length: 255
    )]
    private string $summary;

    // Nom "fonctionnel" de la tâche (ex: moderer_observation, valider_dossier)
    #[ORM\Column(
        name: 'task_name',
        type: 'string',
        length: 150
    )]
    private string $taskName;
    

    // Route Symfony à laquelle envoyer l'utilisateur pour réaliser l'action
    #[ORM\Column(
        name: 'action_route',
        type: 'string',
        length: 255
    )]
    private string $actionRoute;

    // Tâche assignée à un utilisateur précis (optionnel)
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(
        name: 'user_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'SET NULL'
    )]
    private ?User $user = null;

    // Ou assignée à un rôle (ROLE_*)
    #[ORM\Column(
        name: 'role_name',
        type: 'string',
        length: 100,
        nullable: true
    )]
    private ?string $roleName = null;

    #[ORM\Column(
        name: 'created_at',
        type: 'datetime_immutable'
    )]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(
        name: 'completed_at',
        type: 'datetime_immutable',
        nullable: true
    )]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- Getters / Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCaseType(): string
    {
        return $this->caseType;
    }

    public function setCaseType(string $caseType): self
    {
        $this->caseType = $caseType;
        return $this;
    }

    public function getCaseId(): int
    {
        return $this->caseId;
    }

    public function setCaseId(int $caseId): self
    {
        $this->caseId = $caseId;
        return $this;
    }

    public function getProcessName(): string
    {
        return $this->processName;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): self
    {
        $this->summary = $summary;
        return $this;
    }

    public function setProcessName(string $processName): self
    {
        $this->processName = $processName;
        return $this;
    }

    public function getTaskName(): string
    {
        return $this->taskName;
    }

    public function setTaskName(string $taskName): self
    {
        $this->taskName = $taskName;
        return $this;
    }

    public function getActionRoute(): string
    {
        return $this->actionRoute;
    }

    public function setActionRoute(string $actionRoute): self
    {
        $this->actionRoute = $actionRoute;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getRoleName(): ?string
    {
        return $this->roleName;
    }

    public function setRoleName(?string $roleName): self
    {
        $this->roleName = $roleName;
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

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }
}
