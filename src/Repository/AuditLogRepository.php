<?php

/**
 * Open Demat Core – Audit Log Repository
 *
 * Ce repository Doctrine permet d’accéder aux journaux d’audit
 * enregistrés dans l’application. Les entrées d’audit correspondent
 * aux actions réalisées par les utilisateurs ou par le système
 * sur les processus métier (création, modification, transition
 * de workflow, actions diverses, etc.).
 *
 * Il fournit des méthodes utilitaires pour consulter les derniers
 * événements enregistrés, filtrer les logs par processus ou encore
 * retrouver l’historique d’une entité spécifique.
 *
 * Ces informations sont utilisées pour assurer la traçabilité
 * des actions dans l’application et faciliter les opérations
 * d’audit, de suivi ou de diagnostic.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use OpenDemat\Core\Entity\AuditLog;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * @return AuditLog[]
     */
    public function findLatest(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return AuditLog[]
     */
    public function findByProcess(string $processName, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.processName = :processName')
            ->setParameter('processName', $processName)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return AuditLog[]
     */
    public function findByEntity(string $entityType, string $entityId, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}