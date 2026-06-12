<?php

/**
 * Open Demat Core – User Repository
 *
 * Ce repository Doctrine permet de gérer les utilisateurs
 * de la plateforme Core Open Demat ainsi que certaines opérations
 * liées à la sécurité et à la gestion des rôles applicatifs.
 *
 * Il implémente également l’interface PasswordUpgraderInterface
 * afin de permettre la mise à jour automatique du hash des mots
 * de passe lorsque l’algorithme de sécurité évolue.
 *
 * Le repository fournit aussi des méthodes utilitaires permettant :
 * - de récupérer les utilisateurs associés à un processus métier
 *   à partir des rôles applicatifs (ex : ROLE_EXAMPLE_*, ROLE_FINANCE_*)
 * - d’identifier les rôles spécifiques d’un utilisateur pour
 *   un processus donné
 *
 * Ces fonctionnalités facilitent la gestion des habilitations
 * et l’identification des acteurs impliqués dans les différents
 * processus métier de l’application.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Repository;

use OpenDemat\Core\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }

    /**
     * Retourne les utilisateurs ayant au moins un rôle du process demandé.
     *
     * Exemple :
     *  - EXAMPLE => rôles commençant par ROLE_EXAMPLE_
     *  - FINANCE => rôles commençant par ROLE_FINANCE_
     */
    public function findByProcessName(string $processName, bool $excludePowerUser = true): array
    {
        $prefix = $this->buildProcessRolePrefix($processName);
        $powerUserRole = $prefix . 'POWERUSER';

        $users = $this->findAll();

        $filtered = array_filter($users, static function (User $user) use ($prefix, $powerUserRole, $excludePowerUser) {
            $roles = $user->getRoles();

            $hasProcessRole = false;
            foreach ($roles as $role) {
                if (str_starts_with($role, $prefix)) {
                    $hasProcessRole = true;
                    break;
                }
            }

            if (!$hasProcessRole) {
                return false;
            }

            if ($excludePowerUser && in_array($powerUserRole, $roles, true)) {
                return false;
            }

            return true;
        });

        usort($filtered, static function (User $a, User $b) {
            return strcmp(
                (string) ($a->getUsername() ?? ''),
                (string) ($b->getUsername() ?? '')
            );
        });

        return array_values($filtered);
    }

    /**
     * Retourne uniquement les rôles d’un process donné pour un utilisateur.
     *
     * Exemple :
     *  getRolesForProcess($user, 'CSST')
     */
    public function getRolesForProcess(User $user, string $processName, bool $excludePowerUser = false): array
    {
        $prefix = $this->buildProcessRolePrefix($processName);
        $powerUserRole = $prefix . 'POWERUSER';

        $roles = array_filter(
            $user->getRoles(),
            static fn (string $role) => str_starts_with($role, $prefix)
        );

        if ($excludePowerUser) {
            $roles = array_filter(
                $roles,
                static fn (string $role) => $role !== $powerUserRole
            );
        }

        sort($roles);

        return array_values($roles);
    }

    private function buildProcessRolePrefix(string $processName): string
    {
        $normalized = strtoupper(trim($processName));

        return 'ROLE_' . $normalized . '_';
    }
}
