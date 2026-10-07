<?php

declare(strict_types=1);

namespace OpenDemat\Core\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'inbox_message')]
#[ORM\Index(name: 'idx_inbox_user_date', columns: ['user_id', 'created_at', 'id'])]
class InboxMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'text')]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $bundleKey = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;
}
