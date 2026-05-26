<?php

/**
 * Open Demat Core – Référentiel Registry
 *
 * Ce service permet de découvrir automatiquement les entités
 * de référentiel déclarées dans l’application et de construire
 * un registre centralisé de leurs définitions.
 *
 * Il analyse les métadonnées Doctrine afin d’identifier les
 * entités héritant de `AbstractReferentiel`. Pour chacune
 * d’entre elles, une définition (`ReferentielDefinition`)
 * est générée contenant les informations nécessaires à leur
 * exploitation dans l’application :
 * - classe de l’entité
 * - table et schéma en base de données
 * - liste des champs disponibles
 * - liste des champs éditables
 *
 * Ces définitions sont ensuite utilisées par les composants
 * du Core (formulaires, interfaces d’administration, etc.)
 * pour générer dynamiquement les interfaces de gestion
 * des référentiels.
 *
 * Ce mécanisme permet de gérer les données de référence
 * de manière générique sans nécessiter de configuration
 * spécifique pour chaque entité.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Referentiel;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Mapping\ClassMetadata;
use OpenDemat\Core\Entity\Referentiel\AbstractReferentiel;

final class ReferentielRegistry
{
    /** @var array<string, ReferentielDefinition> */
    private array $byKey = [];

    public function __construct(private readonly ManagerRegistry $doctrine)
    {
        $this->warmUp();
    }

    /**
     * Clé stable pour l’URL / menu.
     * Ex: courriers.BatimentRef ou courriers.ref_batiment (à toi de choisir)
     */
    private function makeKey(ClassMetadata $meta): string
    {
        $schema = $this->extractSchema($meta);
        $table  = $meta->getTableName();

        return ($schema ? $schema . '.' : '') . $table;
    }

    private function extractSchema(ClassMetadata $meta): ?string
    {
        // Doctrine stocke souvent le schema dans la propriété table
        // (selon versions/config, schema peut être null)
        return $meta->table['schema'] ?? null;
    }

    private function warmUp(): void
    {
        // On prend un EM “par défaut” (si tu as plusieurs connexions, on peut itérer dessus)
        $em = $this->doctrine->getManager();

        foreach ($em->getMetadataFactory()->getAllMetadata() as $meta) {
            /** @var ClassMetadata $meta */
            $class = $meta->getName();

            // Ignore mappedSuperclass lui-même
            if ($class === AbstractReferentiel::class) {
                continue;
            }

            if (!is_subclass_of($class, AbstractReferentiel::class)) {
                continue;
            }

            $fields = array_keys($meta->fieldMappings);

            // Champs “éditables” par défaut (tu peux affiner)
            $editable = array_values(array_filter($fields, static function (string $f): bool {
                return !in_array($f, ['id', 'createdAt', 'updatedAt'], true);
            }));

            $def = new ReferentielDefinition(
                entityClass: $class,
                shortName: (new \ReflectionClass($class))->getShortName(),
                table: $meta->getTableName(),
                schema: $this->extractSchema($meta),
                fields: $fields,
                editableFields: $editable,
            );

            $this->byKey[$this->makeKey($meta)] = $def;
        }

        ksort($this->byKey);
    }

    /** @return list<ReferentielDefinition> */
    public function all(): array
    {
        return array_values($this->byKey);
    }

    public function get(string $key): ReferentielDefinition
    {
        if (!isset($this->byKey[$key])) {
            throw new \InvalidArgumentException(sprintf('Référentiel inconnu: "%s"', $key));
        }
        return $this->byKey[$key];
    }
}
