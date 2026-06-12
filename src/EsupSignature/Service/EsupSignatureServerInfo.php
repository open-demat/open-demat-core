<?php

namespace OpenDemat\Core\EsupSignature\Service;

use OpenDemat\Core\EsupSignature\ApiCompat\EsupApiAdapterInterface;
use OpenDemat\Core\EsupSignature\ApiCompat\EsupApiAdapterV131;
use OpenDemat\Core\EsupSignature\ApiCompat\EsupApiAdapterV136;

/**
 * Informations sur le serveur ESUP Signature live.
 *
 * Fetch lazy du schéma OpenAPI et de la version via /actuator/info.
 * Tout est mis en cache mémoire pour la durée du processus PHP.
 *
 * Détection de version :
 *   1. info.version dans le schéma OpenAPI (/ws/api-docs)
 *   2. build.version dans /actuator/info (fallback pour v1.36.30+ où info.version est vide)
 */
final class EsupSignatureServerInfo
{
    private ?array $schema  = null;
    private ?string $version = null;
    private ?EsupApiAdapterInterface $adapter = null;

    public function __construct(
        private readonly EsupSignatureApiClient $client,
    ) {}

    public function getSchema(): array
    {
        return $this->schema ??= $this->client->getApiDocs();
    }

    public function getVersion(): string
    {
        if ($this->version !== null) {
            return $this->version;
        }

        try {
            $fromSchema = $this->getSchema()['info']['version'] ?? '';
            if ($fromSchema !== '') {
                return $this->version = $fromSchema;
            }
        } catch (\Throwable) {
        }

        // Fallback actuator — nécessaire depuis la v1.36.30 (info.version vide dans OpenAPI)
        $this->version = $this->client->getActuatorVersion() ?? 'unknown';

        return $this->version;
    }

    public function getAdapter(): EsupApiAdapterInterface
    {
        if ($this->adapter !== null) {
            return $this->adapter;
        }

        $version = $this->getVersion();

        // Comparaison sémantique : >= 1.36.0 → V136
        if ($version !== 'unknown' && version_compare($version, '1.36.0', '>=')) {
            return $this->adapter = new EsupApiAdapterV136();
        }

        return $this->adapter = new EsupApiAdapterV131();
    }

    public function getEndpointCount(): int
    {
        try {
            return count($this->getSchema()['paths'] ?? []);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function invalidate(): void
    {
        $this->schema  = null;
        $this->version = null;
        $this->adapter = null;
    }
}
