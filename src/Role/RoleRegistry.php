<?php

namespace OpenDemat\Core\Role;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\Role;

final class RoleRegistry
{
    public function __construct(
        private readonly RoleSynchronizer $synchronizer,
        private readonly EntityManagerInterface $em,
    ) {}

    public function all(): array
    {
        try {
            $this->synchronizer->sync();
        } catch (\Throwable) {
            return [];
        }

        $repo = $this->em->getRepository(Role::class);

        /** @var Role[] $roles */
        $roles = $repo->findBy(['enabled' => true], ['moduleKey' => 'ASC', 'code' => 'ASC']);

        $out = [];

        foreach ($roles as $role) {
            $out[] = [
                'code' => $role->getCode(),
                'moduleKey' => $role->getModuleKey(),
                'children' => $role->getChildren(),
            ];
        }

        return $out;
    }
}
