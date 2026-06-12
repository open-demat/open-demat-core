<?php

namespace OpenDemat\Core\EsupSignature\Schema;

/**
 * Stockage local et comparaison des schémas OpenAPI d'ESUP Signature.
 *
 * Responsabilité unique : persister sur disque des schémas déjà récupérés
 * et comparer leurs endpoints (/paths).
 * La récupération réseau est assurée par EsupSignatureApiClient::getApiDocs().
 */
final class EsupSchemaCatalog
{
    public function __construct(
        private readonly string $schemaDir,
    ) {}

    public function store(string $version, array $schema): void
    {
        $this->ensureDir();
        file_put_contents(
            $this->filePath($version),
            json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public function load(string $version): ?array
    {
        $path = $this->filePath($version);
        if (!file_exists($path)) {
            return null;
        }
        return json_decode((string) file_get_contents($path), true) ?: null;
    }

    /** @return string[] */
    public function listCached(): array
    {
        $this->ensureDir();
        $versions = [];
        foreach (glob($this->schemaDir . '/api-docs-*.json') ?: [] as $file) {
            if (preg_match('/api-docs-(.+)\.json$/', basename($file), $m)) {
                $versions[] = $m[1];
            }
        }
        sort($versions);
        return $versions;
    }

    /** @return array{added: string[], removed: string[], changed: string[]} */
    public function diff(array $old, array $new): array
    {
        $oldPaths = array_keys($old['paths'] ?? []);
        $newPaths = array_keys($new['paths'] ?? []);

        $added   = array_values(array_diff($newPaths, $oldPaths));
        $removed = array_values(array_diff($oldPaths, $newPaths));

        $changed = [];
        foreach (array_intersect($oldPaths, $newPaths) as $path) {
            $oldMethods = array_keys($old['paths'][$path] ?? []);
            $newMethods = array_keys($new['paths'][$path] ?? []);
            if ($oldMethods !== $newMethods) {
                $changed[] = $path;
                continue;
            }
            foreach ($oldMethods as $method) {
                $oldParams = array_column($old['paths'][$path][$method]['parameters'] ?? [], 'name');
                $newParams = array_column($new['paths'][$path][$method]['parameters'] ?? [], 'name');
                sort($oldParams);
                sort($newParams);
                if ($oldParams !== $newParams) {
                    $changed[] = $path;
                    break;
                }
            }
        }

        return ['added' => $added, 'removed' => $removed, 'changed' => $changed];
    }

    private function filePath(string $version): string
    {
        return $this->schemaDir . '/api-docs-' . $version . '.json';
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->schemaDir)) {
            mkdir($this->schemaDir, 0755, true);
        }
    }
}
