<?php

namespace OpenDemat\Core\Service;

use Aws\S3\S3Client;

class MinioStorage
{
    public function __construct(
        private S3Client $client,
        private string $bucketName,
    ) {
    }

    public function putObject(string $key, string $content, ?string $contentType = null): string
    {
        $params = [
            'Bucket' => $this->bucketName,
            'Key'    => $key,
            'Body'   => $content,
        ];

        if ($contentType) {
            $params['ContentType'] = $contentType;
        }

        $this->client->putObject($params);

        return $key;
    }

    /**
     * Récupère un objet depuis S3.
     *
     * @return array{0: string, 1: string} [contenu, contentType]
     */
    public function getObject(string $key): array
    {
        $result = $this->client->getObject([
            'Bucket' => $this->bucketName,
            'Key'    => $key,
        ]);

        return [
            (string) $result['Body'],
            (string) ($result['ContentType'] ?? 'application/octet-stream'),
        ];
    }

    public function deleteObject(string $key): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucketName,
            'Key'    => $key,
        ]);
    }

    /**
     * Liste toutes les clés S3 sous un préfixe donné (gère la pagination automatiquement).
     *
     * @return string[]
     */
    public function listObjectsByPrefix(string $prefix): array
    {
        $keys   = [];
        $params = ['Bucket' => $this->bucketName, 'Prefix' => $prefix];

        do {
            $result = $this->client->listObjectsV2($params);
            foreach ($result['Contents'] ?? [] as $object) {
                $keys[] = $object['Key'];
            }
            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
        } while ($result['IsTruncated'] ?? false);

        return $keys;
    }

    /**
     * Supprime plusieurs objets S3 en lots de 100 (rafraîchissement barre de progression).
     *
     * @param string[]      $keys
     * @param callable|null $onChunkDeleted callable(int $chunkDeleted): void — appelé après chaque lot
     * @return int nombre d'objets effectivement supprimés
     */
    public function deleteObjects(array $keys, ?callable $onChunkDeleted = null): int
    {
        if ($keys === []) {
            return 0;
        }

        $deleted = 0;
        foreach (array_chunk($keys, 100) as $chunk) {
            $result       = $this->client->deleteObjects([
                'Bucket' => $this->bucketName,
                'Delete' => [
                    'Objects' => array_map(fn(string $k) => ['Key' => $k], $chunk),
                    'Quiet'   => true,
                ],
            ]);
            $chunkDeleted = count($chunk) - count($result['Errors'] ?? []);
            $deleted     += $chunkDeleted;
            if ($onChunkDeleted !== null) {
                ($onChunkDeleted)($chunkDeleted);
            }
        }

        return $deleted;
    }

    public function getPresignedUrl(string $key, int $expiresInSeconds = 3600): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucketName,
            'Key'    => $key,
        ]);

        $request = $this->client->createPresignedRequest($cmd, '+' . $expiresInSeconds . ' seconds');

        return (string) $request->getUri();
    }
}
