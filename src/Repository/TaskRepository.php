<?php

/**
 * Open Demat Core – Task Repository
 *
 * Ce repository Doctrine permet de gérer les tâches associées
 * aux processus métier de la plateforme Core Open Demat.
 *
 * Les entités `Task` représentent des actions à réaliser par
 * un utilisateur ou par un groupe d’utilisateurs identifié
 * par un rôle. Elles sont généralement générées lors de certaines
 * étapes d’un processus (workflow) afin d’assigner une action
 * à un acteur spécifique.
 *
 * Le repository fournit des méthodes permettant notamment :
 * - de récupérer les tâches ouvertes pour un utilisateur
 *   ou pour les rôles qu’il possède
 * - de compter les tâches en attente
 * - de marquer automatiquement certaines tâches comme terminées
 *   pour un dossier donné
 *
 * Ce mécanisme constitue la base du système de gestion
 * des tâches et des boîtes de réception des utilisateurs
 * dans l’application.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use OpenDemat\Core\Entity\Task;
use OpenDemat\Core\Entity\User;

class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Tâches non terminées pour un user + une liste de rôles (déjà expandés).
     */
    public function findOpenForUserAndRoleNames(User $user, array $roleNames): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.completedAt IS NULL')
            ->orderBy('t.createdAt', 'ASC')
            ->setParameter('user', $user);

        if (!empty($roleNames)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    't.user = :user',
                    '(t.roleName IS NOT NULL AND t.roleName IN (:roles))'
                )
            )
            ->setParameter('roles', $roleNames);
        } else {
            $qb->andWhere('t.user = :user');
        }

        return $qb->getQuery()->getResult();
    }

    public function countOpenForUserAndRoleNames(User $user, array $roleNames): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.completedAt IS NULL')
            ->setParameter('user', $user);

        if (!empty($roleNames)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    't.user = :user',
                    '(t.roleName IS NOT NULL AND t.roleName IN (:roles))'
                )
            )
            ->setParameter('roles', array_values(array_unique($roleNames)));
        } else {
            $qb->andWhere('t.user = :user');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function completeOpenForCaseAndTaskNames(
    string $caseType,
    int $caseId,
    array $taskNames
    ): int {
        if ($taskNames === []) return 0;

        return $this->createQueryBuilder('t')
            ->update()
            ->set('t.completedAt', ':now')
            ->where('t.completedAt IS NULL')
            ->andWhere('t.caseType = :ctype')
            ->andWhere('t.caseId = :cid')
            ->andWhere('t.taskName IN (:names)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('ctype', $caseType)
            ->setParameter('cid', $caseId)
            ->setParameter('names', $taskNames)
            ->getQuery()
            ->execute();
    }
}
