<?php

/**
 * Open Demat Core – Application Entity
 *
 * Cette entité représente une application enregistrée dans le portail
 * Core Open Demat. Elle permet de décrire les différentes applications
 * disponibles dans la plateforme (modules métiers, outils internes,
 * services administratifs, etc.).
 *
 * Chaque application possède une clé unique, un titre, une route
 * d’accès, les rôles nécessaires pour y accéder, ainsi que diverses
 * informations d’affichage utilisées dans l’interface du portail.
 *
 * Cette entité est notamment utilisée par le système de registre
 * d’applications afin de construire dynamiquement le catalogue des
 * services accessibles aux utilisateurs.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'application')]
class Application
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private string $key;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $route;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $baseUrl = null; // si un jour tu veux gérer ça côté DB

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    // + getters/setters...

    public function getKey(): string { return $this->key; }
    public function setKey(string $key): self { $this->key = $key; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getRoute(): string { return $this->route; }
    public function setRoute(string $route): self { $this->route = $route; return $this; }

    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $icon): self { $this->icon = $icon; return $this; }

    public function getRoles(): array { return $this->roles; }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }

    public function getBaseUrl(): ?string { return $this->baseUrl; }
    public function setBaseUrl(?string $baseUrl): self { $this->baseUrl = $baseUrl; return $this; }
}
