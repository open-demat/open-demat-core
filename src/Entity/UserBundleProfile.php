<?php

declare(strict_types=1);

namespace OpenDemat\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use OpenDemat\Core\Repository\UserBundleProfileRepository;

#[ORM\Entity(repositoryClass: UserBundleProfileRepository::class)]
#[ORM\Table(name: 'user_bundle_profile')]
#[ORM\UniqueConstraint(name: 'uniq_user_bundle_profile', columns: ['user_id', 'bundle_key'])]
class UserBundleProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'bundle_key', type: 'string', length: 50)]
    private string $bundleKey;

    #[ORM\Column(name: 'notifications_muted', type: 'boolean', options: ['default' => false])]
    private bool $notificationsMuted = false;

    public function __construct(User $user, string $bundleKey)
    {
        $this->user = $user;
        $this->setBundleKey($bundleKey);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getBundleKey(): string
    {
        return $this->bundleKey;
    }

    public function setBundleKey(string $bundleKey): self
    {
        $bundleKey = strtoupper(trim($bundleKey));
        if ($bundleKey === '') {
            throw new \InvalidArgumentException('La clé du bundle ne peut pas être vide.');
        }

        $this->bundleKey = $bundleKey;

        return $this;
    }

    public function isNotificationsMuted(): bool
    {
        return $this->notificationsMuted;
    }

    public function setNotificationsMuted(bool $notificationsMuted): self
    {
        $this->notificationsMuted = $notificationsMuted;

        return $this;
    }
}
