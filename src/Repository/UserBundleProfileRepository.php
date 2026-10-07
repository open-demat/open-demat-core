<?php

declare(strict_types=1);

namespace OpenDemat\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Entity\UserBundleProfile;

/**
 * @extends ServiceEntityRepository<UserBundleProfile>
 */
final class UserBundleProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBundleProfile::class);
    }

    /**
     * @return array<string, UserBundleProfile>
     */
    public function findIndexedByBundleForUser(User $user): array
    {
        $profiles = $this->findBy(['user' => $user]);
        $indexed = [];

        foreach ($profiles as $profile) {
            $indexed[$profile->getBundleKey()] = $profile;
        }

        return $indexed;
    }
}
