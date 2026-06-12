<?php

namespace OpenDemat\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use OpenDemat\Core\Entity\Role;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * @return Role[]
     */
    public function findEnabledRoles(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('r.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Role[]
     */
    public function findEnabledRolesByModule(?string $moduleKey): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('r.code', 'ASC');

        if ($moduleKey === null) {
            $qb->andWhere('r.moduleKey IS NULL');
        } else {
            $qb->andWhere('r.moduleKey = :moduleKey')
               ->setParameter('moduleKey', $moduleKey);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneEnabledByCode(string $code): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.code = :code')
            ->andWhere('r.enabled = :enabled')
            ->setParameter('code', $code)
            ->setParameter('enabled', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne la liste plate des codes disponibles
     * à partir des rôles actifs + leurs children.
     *
     * @return string[]
     */
    public function getAvailableRoleCodes(): array
    {
        $roles = $this->findEnabledRoles();

        $flat = [];

        foreach ($roles as $role) {
            $flat[] = $role->getCode();

            foreach ($role->getChildren() as $childCode) {
                if (\is_string($childCode) && $childCode !== '') {
                    $flat[] = $childCode;
                }
            }
        }

        $flat[] = 'ROLE_USER';

        $flat = array_values(array_unique($flat));
        sort($flat);

        return $flat;
    }

    /**
     * Retourne la liste plate des codes disponibles
     * pour un module donné.
     *
     * @return string[]
     */
    public function getAvailableRoleCodesByModule(?string $moduleKey): array
    {
        $roles = $this->findEnabledRolesByModule($moduleKey);

        $flat = [];

        foreach ($roles as $role) {
            $flat[] = $role->getCode();

            foreach ($role->getChildren() as $childCode) {
                if (\is_string($childCode) && $childCode !== '') {
                    $flat[] = $childCode;
                }
            }
        }

        $flat[] = 'ROLE_USER';

        $flat = array_values(array_unique($flat));
        sort($flat);

        return $flat;
    }

    /**
     * Retourne une hiérarchie aplatie récursive.
     * Exemple :
     * ROLE_ADMIN => [ROLE_MANAGER]
     * ROLE_MANAGER => [ROLE_USER]
     *
     * donnera :
     * [ROLE_ADMIN, ROLE_MANAGER, ROLE_USER]
     *
     * @return string[]
     */
    public function getFlattenedRoleHierarchy(): array
    {
        $roles = $this->findEnabledRoles();

        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[$role->getCode()] = $role;
        }

        $flat = [];

        foreach ($roles as $role) {
            $this->flattenRole($role, $roleMap, $flat, []);
        }

        $flat[] = 'ROLE_USER';

        $flat = array_values(array_unique($flat));
        sort($flat);

        return $flat;
    }

    /**
     * Retourne la hiérarchie complète sous forme :
     * [
     *   'ROLE_ADMIN' => ['ROLE_MANAGER', 'ROLE_USER'],
     *   'ROLE_MANAGER' => ['ROLE_USER'],
     * ]
     *
     * @return array<string, string[]>
     */
    public function getRoleHierarchyMap(): array
    {
        $roles = $this->findEnabledRoles();

        $map = [];

        foreach ($roles as $role) {
            $children = array_values(array_filter(
                $role->getChildren(),
                static fn ($child) => \is_string($child) && $child !== ''
            ));

            $map[$role->getCode()] = $children;
        }

        ksort($map);

        return $map;
    }

    /**
     * @param array<string, Role> $roleMap
     * @param string[] $flat
     * @param string[] $visited
     */
    private function flattenRole(Role $role, array $roleMap, array &$flat, array $visited): void
    {
        $code = $role->getCode();

        if (\in_array($code, $visited, true)) {
            return;
        }

        $visited[] = $code;
        $flat[] = $code;

        foreach ($role->getChildren() as $childCode) {
            if (!\is_string($childCode) || $childCode === '') {
                continue;
            }

            $flat[] = $childCode;

            if (isset($roleMap[$childCode])) {
                $this->flattenRole($roleMap[$childCode], $roleMap, $flat, $visited);
            }
        }
    }
}