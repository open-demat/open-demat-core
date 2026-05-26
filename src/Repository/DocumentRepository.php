<?php

/**
 * Open Demat Core – Document Repository
 *
 * Ce repository Doctrine permet d’accéder aux entités Document
 * stockées dans l’application. Les documents représentent les
 * fichiers téléversés par les utilisateurs ou générés par les
 * différents processus métier de la plateforme.
 *
 * Chaque document contient les métadonnées nécessaires à la gestion
 * des fichiers : nom d’origine, type MIME, taille, checksum et
 * emplacement de stockage (bucket).
 *
 * Les fichiers peuvent ensuite être associés à des processus,
 * des utilisateurs ou d’autres entités métier via les mécanismes
 * du Core (ProcessAttachment, UserDocument, etc.).
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use OpenDemat\Core\Entity\Document;

class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }
}
