<?php

/**
 * Open Demat Core – Process Registry
 *
 * Ce service permet de découvrir et enregistrer automatiquement
 * l’ensemble des processus métier déclarés dans l’application.
 * Il analyse les entités Doctrine héritant de `AbstractProcessEntity`
 * et portant l’attribut `ProcessDefinition` afin de construire
 * un registre centralisé des processus disponibles.
 *
 * Le registre permet ensuite d’accéder aux métadonnées des processus
 * (classe entité, table, champs affichés, rôles autorisés, workflow, etc.)
 * utilisées par les différents composants du Core : interfaces dynamiques,
 * génération de formulaires, gestion des workflows ou accès aux données.
 *
 * Cette approche permet de déclarer les processus directement dans
 * le code via des attributs PHP, tout en offrant un point d’accès
 * centralisé aux définitions de processus dans l’application.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Process;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use ReflectionClass;
use OpenDemat\Core\Process\Attribute\ProcessDefinition;

final class ProcessRegistry
{
    /** @var array<string, ProcessEntityDefinition> */
    private array $definitions = [];

    public function __construct(
        private readonly ManagerRegistry $doctrine,
    ) {
        // On scanne tous les managers (utile si tes entités sont sur plusieurs EM)
        foreach ($this->doctrine->getManagers() as $em) {
            /** @var list<ClassMetadata> $all */
            $all = $em->getMetadataFactory()->getAllMetadata();

            foreach ($all as $meta) {
                $class = $meta->getName();

                if (!is_subclass_of($class, AbstractProcessEntity::class)) {
                    continue;
                }

                $r = new ReflectionClass($class);
                $attrs = $r->getAttributes(ProcessDefinition::class);
                if (!$attrs) {
                    continue;
                }

                /** @var ProcessDefinition $attr */
                $attr = $attrs[0]->newInstance();

                // Table / schema via metadata doctrine
                $table = (string) ($meta->getTableName() ?? '');
                $schema = null;

                // Doctrine ORM metadata: schema peut être dans $meta->table['schema']
                // (ça dépend config + driver)
                if (isset($meta->table['schema']) && is_string($meta->table['schema']) && $meta->table['schema'] !== '') {
                    $schema = $meta->table['schema'];
                }

                // ShortName
                $shortName = $r->getShortName();

                $def = new ProcessEntityDefinition(
                    key: $attr->key,
                    entityClass: $class,
                    shortName: $shortName,
                    table: $table,
                    schema: $schema,
                    label: $attr->label ?? null,
                    listFields: $attr->listFields ?? [],
                    formFields: $attr->formFields ?? [],
                    roles: $attr->roles ?? [],
                    workflow: $attr->workflow ?? null,
                );

                // Prévenir les collisions de key (si deux entités ont la même key)
                if (isset($this->definitions[$def->key]) && $this->definitions[$def->key]->entityClass !== $class) {
                    throw new \LogicException(sprintf(
                        "Collision ProcessDefinition key='%s' entre %s et %s",
                        $def->key,
                        $this->definitions[$def->key]->entityClass,
                        $class
                    ));
                }

                $this->definitions[$def->key] = $def;
            }
        }

        // ordre stable
        ksort($this->definitions);
    }

    /** @return list<ProcessEntityDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function get(string $key): ProcessEntityDefinition
    {
        if (!isset($this->definitions[$key])) {
            throw new \InvalidArgumentException("Process '$key' introuvable.");
        }
        return $this->definitions[$key];
    }

    /** @return class-string<AbstractProcessEntity> */
    public function classFor(string $key): string
    {
        return $this->get($key)->entityClass;
    }
}
