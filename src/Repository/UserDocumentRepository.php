<?php

/**
 * Open Demat Core – User Document Repository
 *
 * Ce repository Doctrine permet de gérer les documents associés
 * aux utilisateurs dans le système de stockage personnel
 * ("vault utilisateur") de la plateforme Core Open Demat.
 *
 * Les entités `UserDocument` représentent les fichiers appartenant
 * à un utilisateur et conservés dans son espace documentaire.
 * Ces documents peuvent correspondre par exemple à des pièces
 * d’identité, RIB, justificatifs ou tout autre fichier personnel
 * utilisé dans les différents processus métier.
 *
 * Le repository fournit notamment des méthodes permettant :
 * - de récupérer la liste des documents d’un utilisateur,
 *   avec un tri privilégiant les documents épinglés
 * - de vérifier si un document est déjà associé à un utilisateur
 *
 * Ce mécanisme permet de centraliser les documents personnels
 * et de les réutiliser dans différents processus applicatifs.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use OpenDemat\Core\Entity\Document;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Entity\UserDocument;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserDocument>
 */
class UserDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserDocument::class);
    }

    /**
     * Liste “vault” d’un user avec tri utile (pinned d’abord, puis récents).
     *
     * @return UserDocument[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('ud')
            ->andWhere('ud.user = :u')
            ->setParameter('u', $user)
            ->orderBy('ud.isPinned', 'DESC')
            ->addOrderBy('ud.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function existsByDocument(Document $document): bool
    {
        return (bool) $this->createQueryBuilder('ud')
            ->select('1')
            ->andWhere('ud.document = :doc')
            ->setParameter('doc', $document)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
