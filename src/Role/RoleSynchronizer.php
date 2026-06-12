<?php

namespace OpenDemat\Core\Role;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\Role;

final class RoleSynchronizer
{
    private bool $synced = false;

    /**
     * @param array<string, array<int, string>> $roleHierarchy
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly array $roleHierarchy,
    ) {}

    public function sync(): void
    {
        if ($this->synced) {
            return;
        }

        $this->synced = true;

        $repo = $this->em->getRepository(Role::class);

        /** @var Role[] $all */
        $all = $repo->findAll();

        $existing = [];
        foreach ($all as $role) {
            $existing[$role->getCode()] = $role;
        }

        /*
         * On construit la liste complète des rôles à synchroniser.
         *
         * Important :
         * - Les rôles parents sont ajoutés.
         * - Les rôles enfants sont aussi ajoutés.
         *
         * Sans ça, un rôle présent uniquement comme enfant dans role_hierarchy
         * pourrait ne pas être correctement créé ou réactivé.
         */
        $rolesToSync = [];

        foreach ($this->roleHierarchy as $roleCode => $children) {
            if (!$this->isSyncableRole($roleCode)) {
                continue;
            }

            $children = array_values(array_filter(
                array_unique($children),
                fn (string $child): bool => $this->isSyncableRole($child)
            ));

            sort($children);

            $rolesToSync[$roleCode] = $children;

            foreach ($children as $child) {
                if (!isset($rolesToSync[$child])) {
                    $rolesToSync[$child] = [];
                }
            }
        }

        foreach ($rolesToSync as $roleCode => $children) {
            $moduleKey = $this->extractModuleKey($roleCode);

            if (!isset($existing[$roleCode])) {
                $role = (new Role())
                    ->setCode($roleCode)
                    ->setModuleKey($moduleKey)
                    ->setChildren($children)
                    ->setEnabled(true);

                $this->em->persist($role);
                continue;
            }

            $role = $existing[$roleCode];
            $updated = false;

            if ($role->getModuleKey() !== $moduleKey) {
                $role->setModuleKey($moduleKey);
                $updated = true;
            }

            $currentChildren = $role->getChildren();
            sort($currentChildren);

            if ($currentChildren !== $children) {
                $role->setChildren($children);
                $updated = true;
            }

            if (!$role->isEnabled()) {
                $role->setEnabled(true);
                $updated = true;
            }

            if ($updated) {
                $this->em->persist($role);
            }
        }

        /*
         * NE PAS désactiver les rôles absents de cette instance.
         *
         * Plusieurs instances Symfony peuvent partager la même base.
         * Une instance peut ne connaître que ROLE_ADMIN / ROLE_USER,
         * tandis qu'une autre connaît des rôles métier de bundles optionnels.
         *
         * Si on désactive les rôles non présents dans la hiérarchie courante,
         * une instance peut désactiver les rôles créés par une autre.
         */
        $this->em->flush();
    }

    private function isSyncableRole(string $role): bool
    {
        return str_starts_with($role, 'ROLE_');
    }

    private function extractModuleKey(string $role): ?string
    {
        if (!str_starts_with($role, 'ROLE_')) {
            return null;
        }

        if (preg_match('/^ROLE_([A-Z0-9]+)_/', $role, $matches)) {
            return $matches[1] ?? 'CORE';
        }

        return 'CORE';
    }
}
