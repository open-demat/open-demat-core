<?php

namespace OpenDemat\Core\EsupSignature\Security;

final class BpmSignalSecretRegistry
{
    /** @var array<string, string> caseType → secret */
    private array $secrets = [];

    /** @param iterable<BpmSignalProviderInterface> $providers */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->secrets[$provider->getCaseType()] = $provider->getSignalSecret();
        }
    }

    /** Retourne null si le caseType n'est pas enregistré (→ 403). */
    public function getSecret(string $caseType): ?string
    {
        return $this->secrets[$caseType] ?? null;
    }
}
