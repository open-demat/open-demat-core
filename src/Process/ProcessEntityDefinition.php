<?php

/**
 * Open Demat Core – Process Entity Definition
 *
 * Cette classe représente la définition d’une entité de processus
 * dans le système de gestion des processus du Core Open Demat.
 * Elle contient les métadonnées décrivant un processus métier
 * et son entité associée, telles que la classe Doctrine,
 * le nom de la table, les champs à afficher dans les listes
 * et formulaires, les rôles autorisés ainsi que le workflow
 * éventuellement associé.
 *
 * Les instances de cette classe sont généralement construites
 * à partir des attributs `ProcessDefinition` présents sur les
 * entités métier et utilisées par le registre des processus
 * pour piloter dynamiquement les interfaces et comportements
 * applicatifs.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Process;

final class ProcessEntityDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $entityClass,
        public readonly string $shortName,
        public readonly string $table,
        public readonly ?string $schema,
        public readonly ?string $label = null,
        /** @var list<string> */
        public readonly array $listFields = [],
        /** @var list<string> */
        public readonly array $formFields = [],
        /** @var list<string> */
        public readonly array $roles = [],
        public readonly ?string $workflow = null,
    ) {}
}
