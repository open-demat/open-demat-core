<?php

/**
 * Open Demat Core – User Entity
 *
 * Cette entité représente un utilisateur de la plateforme Core Open Demat.
 * Elle contient les informations d’authentification, les rôles de sécurité
 * ainsi que les données personnelles et professionnelles nécessaires au
 * fonctionnement des différents modules applicatifs.
 *
 * Elle implémente les interfaces de sécurité Symfony afin d’être utilisée
 * dans le système d’authentification et de gestion des accès de la
 * plateforme.
 *
 * Cette entité sert de base commune pour l’ensemble des applications
 * intégrées au portail Open Demat et permet de centraliser la gestion des
 * utilisateurs, de leurs rôles et de leurs informations métier.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $sessionVersion = 1;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $username;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $roles = [];

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $password = null;

    // === Infos personnelles ===

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $fonction = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $site = null;

    // === Données métier ===

    // Plusieurs composantes possibles
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $composante = null;

    // Plusieurs départements possibles
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $dep_composante = null;

    // --- Getters / Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    // === Session version ===

    public function getSessionVersion(): int
    {
        return $this->sessionVersion;
    }

    public function setSessionVersion(int $sessionVersion): self
    {
        $this->sessionVersion = $sessionVersion;

        return $this;
    }

    public function bumpSessionVersion(): self
    {
        ++$this->sessionVersion;

        return $this;
    }



    // === Infos personnelles ===

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getFonction(): ?string
    {
        return $this->fonction;
    }

    public function setFonction(?string $fonction): self
    {
        $this->fonction = $fonction;
        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): self
    {
        $this->service = $service;
        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(?string $site): self
    {
        $this->site = $site;
        return $this;
    }

    // === Sécurité ===

    public function getRoles(): array
    {
        $roles = $this->roles;
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    // === Métier ===

    public function getComposante(): array
    {
        return $this->composante ?? [];
    }

    public function setComposante(?array $composante): self
    {
        $this->composante = $composante;
        return $this;
    }

    public function getDepComposante(): array
    {
        return $this->dep_composante ?? [];
    }

    public function setDepComposante(?array $dep): self
    {
        $this->dep_composante = $dep;
        return $this;
    }

    public function eraseCredentials(): void {}
}
