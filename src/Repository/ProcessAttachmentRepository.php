<?php

/**
 * Open Demat Core – Process Attachment Repository
 *
 * Ce repository Doctrine permet de gérer les associations entre
 * les documents stockés dans l’application et les dossiers
 * appartenant à un processus métier.
 *
 * Les entités `ProcessAttachment` servent de lien générique entre
 * un document (`Document`) et un dossier identifié par :
 * - un nom de processus
 * - un type de dossier
 * - un identifiant de dossier
 *
 * Ce mécanisme permet de rattacher des fichiers à n’importe quel
 * processus métier (CSST, remboursements, courriers, etc.) sans
 * dépendre d’une entité spécifique.
 *
 * Le repository fournit également des méthodes utilitaires pour :
 * - récupérer les pièces jointes d’un dossier
 * - compter les documents associés
 * - rechercher des pièces jointes pour l’administration
 * - lister les processus et types de dossiers existants
 * - vérifier l’existence d’un document déjà attaché
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use OpenDemat\Core\Entity\Document;
use OpenDemat\Core\Entity\ProcessAttachment;

class ProcessAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessAttachment::class);
    }

    /** @return ProcessAttachment[] */
    public function findForCase(string $processName, string $caseType, int $caseId): array
    {
        return $this->createQueryBuilder('pa')
            ->andWhere('pa.processName = :p')->setParameter('p', $processName)
            ->andWhere('pa.caseType = :t')->setParameter('t', $caseType)
            ->andWhere('pa.caseId = :id')->setParameter('id', $caseId)
            ->orderBy('pa.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countForCase(string $processName, string $caseType, int $caseId): int
    {
        return (int) $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->andWhere('pa.processName = :p')->setParameter('p', $processName)
            ->andWhere('pa.caseType = :t')->setParameter('t', $caseType)
            ->andWhere('pa.caseId = :id')->setParameter('id', $caseId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return string[] */
    public function listDistinctProcesses(): array
    {
        $rows = $this->createQueryBuilder('pa')
            ->select('DISTINCT pa.processName AS p')
            ->orderBy('p', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(fn($r) => (string) $r['p'], $rows));
    }

    /** @return string[] */
    public function listDistinctTypesForProcess(string $processName): array
    {
        $rows = $this->createQueryBuilder('pa')
            ->select('DISTINCT pa.caseType AS t')
            ->andWhere('pa.processName = :p')->setParameter('p', $processName)
            ->orderBy('t', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(fn($r) => (string) $r['t'], $rows));
    }

    /** @return ProcessAttachment[] */
    public function searchForAdmin(string $processName, string $caseType, string $q = '', int $limit = 300): array
    {
        $qb = $this->createQueryBuilder('pa')
            ->addSelect('d', 'cb', 'ub')
            ->innerJoin('pa.document', 'd')
            ->leftJoin('pa.createdBy', 'cb')
            ->leftJoin('d.uploadedBy', 'ub')
            ->andWhere('pa.processName = :p')->setParameter('p', $processName)
            ->andWhere('pa.caseType = :t')->setParameter('t', $caseType)
            ->orderBy('pa.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($q !== '') {
            $qb->andWhere('
                d.originalName LIKE :q
                OR CAST(pa.caseId AS string) LIKE :q
                OR cb.username LIKE :q
                OR cb.email LIKE :q
                OR ub.username LIKE :q
                OR ub.email LIKE :q
            ')
            ->setParameter('q', '%' . $q . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function existsForCaseAndDocument(string $processName, string $caseType, int $caseId, Document $document): bool
    {
        return (bool) $this->createQueryBuilder('pa')
            ->select('1')
            ->andWhere('pa.processName = :p')
            ->andWhere('pa.caseType = :t')
            ->andWhere('pa.caseId = :cid')
            ->andWhere('pa.document = :doc')
            ->setParameter('p', $processName)
            ->setParameter('t', $caseType)
            ->setParameter('cid', $caseId)
            ->setParameter('doc', $document)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByDocument(Document $document): int
    {
        return (int) $this->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->andWhere('pa.document = :doc')
            ->setParameter('doc', $document)
            ->getQuery()
            ->getSingleScalarResult();
    }

}
