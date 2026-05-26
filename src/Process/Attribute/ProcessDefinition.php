<?php

/**
 * Open Demat Core – Process Definition Attribute
 *
 * Cet attribut PHP permet de déclarer et de configurer un processus
 * métier dans la plateforme Core Open Demat directement au niveau d’une
 * classe. Il sert à décrire les métadonnées nécessaires au
 * fonctionnement des modules basés sur le système de processus
 * générique du Core.
 *
 * Il permet notamment de définir l’identifiant du processus,
 * son libellé, les rôles autorisés, les champs à afficher dans
 * les listes et formulaires, ainsi que le workflow associé.
 *
 * Cet attribut est utilisé par le système de registre des processus
 * afin de découvrir automatiquement les processus disponibles et
 * d’alimenter dynamiquement les interfaces applicatives.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Process\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ProcessDefinition
{
    /**
     * @param list<string> $roles
     * @param list<string> $listFields
     * @param list<string> $formFields
     */
    public function __construct(
        public readonly string $key,
        public readonly ?string $label = null,
        public readonly array $roles = [],
        public readonly array $listFields = [],
        public readonly array $formFields = [],
        public readonly ?string $workflow = null,
    ) {}
}
