<?php

namespace OpenDemat\Core\EsupSignature\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use OpenDemat\Core\EsupSignature\Exception\EsLoginRedirectException;
use OpenDemat\Core\EsupSignature\Exception\EsUnexpectedResponseException;

/**
 * Client HTTP pour l'API ESUP Signature.
 *
 * Authentification : header X-Api-Key sur toutes les requêtes.
 * Format corps (POST non-fichier) : application/x-www-form-urlencoded.
 * Format corps (POST avec fichier) : multipart/form-data manuel (voir MULTIPART-PROTOCOL.md).
 */
class EsupSignatureApiClient
{
    public const PATH_API_DOCS    = '/ws/api-docs';
    public const PATH_ACTUATOR    = '/actuator/info';

    private const HEADERS_DEFAULT = [
        'Accept' => 'application/json',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeout,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array|string
     */
    public function get(string $path, array $query = []): array|string
    {
        $url = $this->buildUrl($path);
        $this->logger->debug(sprintf('[ES] GET %s', $url), $query);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->headers(),
            'query'   => $query,
            'timeout' => $this->timeout,
        ]);

        $contentType = $response->getHeaders()['content-type'][0] ?? '';

        if (str_contains($contentType, 'application/json')) {
            try {
                return $response->toArray();
            } catch (\JsonException $e) {
                $this->logger->warning(sprintf(
                    '[ES] GET %s — corps non-JSON malgré Content-Type application/json : %s',
                    $url, $e->getMessage()
                ));
                return $response->getContent();
            }
        }

        return $response->getContent();
    }

    public function post(string $path, array $body = []): array
    {
        $url = $this->buildUrl($path);
        $this->logger->debug(sprintf('[ES] POST %s', $url), array_keys($body));

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $this->headers(['Content-Type' => 'application/x-www-form-urlencoded']),
            'body'    => $body,
            'timeout' => $this->timeout,
        ]);

        return $response->toArray();
    }

    /**
     * POST multipart avec fichier.
     *
     * Construit le multipart manuellement (format navigateur RFC 7578) car
     * Symfony FormDataPart ajoute des headers MIME que Tomcat/Spring ne décode pas.
     *
     * @param array<string, string> $fields
     */
    public function postFile(string $path, string $filePath, string $fileName, array $fields = []): array
    {
        $url      = $this->buildUrl($path);
        $boundary = '----PhpBoundary' . bin2hex(random_bytes(12));
        $body     = '';

        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= (string) $value . "\r\n";
        }

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"multipartFiles\"; filename=\"{$fileName}\"\r\n";
        $body .= "Content-Type: application/pdf\r\n\r\n";
        $body .= file_get_contents($filePath) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $this->logger->debug(sprintf('[ES] POST (multipart) %s — fichier: %s', $url, $fileName));

        $response = $this->httpClient->request('POST', $url, [
            'headers' => array_merge($this->headers(), [
                'Content-Type' => "multipart/form-data; boundary={$boundary}",
            ]),
            'body'    => $body,
            'timeout' => $this->timeout,
        ]);

        try {
            return $response->toArray();
        } catch (\JsonException $e) {
            $raw = trim($response->getContent());
            if (is_numeric($raw)) {
                return ['id' => (int) $raw];
            }
            throw $e;
        }
    }

    public function delete(string $path): void
    {
        $url = $this->buildUrl($path);
        $this->logger->debug(sprintf('[ES] DELETE %s', $url));

        $this->httpClient->request('DELETE', $url, [
            'headers' => $this->headers(),
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * @throws EsLoginRedirectException
     * @throws EsUnexpectedResponseException
     */
    public function getApiDocs(): array
    {
        $raw = $this->get(self::PATH_API_DOCS);

        if (!is_array($raw)) {
            $preview = substr(ltrim((string) $raw), 0, 30);
            $isHtml  = str_starts_with($preview, '<!DOCTYPE') || str_starts_with($preview, '<html');
            throw $isHtml
                ? new EsLoginRedirectException(self::PATH_API_DOCS)
                : new EsUnexpectedResponseException(self::PATH_API_DOCS, $preview);
        }

        return $raw;
    }

    /**
     * Interroge /actuator/info pour lire la version buildée du serveur.
     * Fallback nécessaire car info.version est vide dans le schéma OpenAPI depuis la v1.36.30.
     */
    public function getActuatorVersion(): ?string
    {
        try {
            $data = $this->get(self::PATH_ACTUATOR);
            return is_array($data) ? ($data['build']['version'] ?? null) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    private function buildUrl(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function headers(array $extra = []): array
    {
        return array_merge(self::HEADERS_DEFAULT, ['X-Api-Key' => $this->apiKey], $extra);
    }
}
