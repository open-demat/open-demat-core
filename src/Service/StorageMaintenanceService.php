<?php

namespace OpenDemat\Core\Service;

/**
 * Opérations d'administration brute du stockage S3 (sans tracking DB).
 *
 * Point d'entrée réservé aux commandes de maintenance et de nettoyage.
 * Les bundles injectent ce service plutôt que MinioStorage directement.
 */
class StorageMaintenanceService
{
    public function __construct(private readonly MinioStorage $storage)
    {
    }

    /**
     * Retourne toutes les clés S3 dont le nom commence par $prefix.
     *
     * @return string[]
     */
    public function listByPrefix(string $prefix): array
    {
        return $this->storage->listObjectsByPrefix($prefix);
    }

    /**
     * Supprime en bloc une liste de clés S3.
     *
     * @param string[]      $keys
     * @param callable|null $onChunkDeleted callable(int $deleted): void — progression par lot
     */
    public function deleteObjects(array $keys, ?callable $onChunkDeleted = null): int
    {
        return $this->storage->deleteObjects($keys, $onChunkDeleted);
    }

    /**
     * Liste puis supprime tous les objets S3 sous un préfixe donné.
     *
     * @param callable|null $onChunkDeleted callable(int $deleted): void
     */
    public function deleteByPrefix(string $prefix, ?callable $onChunkDeleted = null): int
    {
        $keys = $this->storage->listObjectsByPrefix($prefix);

        return $this->storage->deleteObjects($keys, $onChunkDeleted);
    }
}
