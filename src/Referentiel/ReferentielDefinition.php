<?php

/**
 * Open Demat Core – Référentiel Definition
 *
 * Cette classe représente la définition d’un référentiel métier
 * dans la plateforme Core Open Demat. Elle contient les informations
 * nécessaires pour décrire une entité de référentiel et permettre
 * son exploitation dynamique dans l’application.
 *
 * Une définition de référentiel inclut notamment la classe de
 * l’entité Doctrine associée, le nom de la table en base de données,
 * le schéma éventuel, ainsi que la liste des champs disponibles
 * et ceux pouvant être modifiés dans les interfaces d’administration.
 *
 * Ces définitions sont utilisées par le système de registre des
 * référentiels afin de générer automatiquement les interfaces
 * de consultation et de gestion des données de référence.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Referentiel;

final class ReferentielDefinition
{
    public function __construct(
        public readonly string $entityClass,
        public readonly string $shortName,
        public readonly string $table,
        public readonly ?string $schema,
        /** @var list<string> */
        public readonly array $fields,
        /** @var list<string> */
        public readonly array $editableFields,
    ) {}
}
