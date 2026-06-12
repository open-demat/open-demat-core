<?php

namespace OpenDemat\Core\EsupSignature\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Construit le targetUrl HMAC à injecter dans les requêtes ESUP Signature.
 *
 * Le token est statique (calculé une fois par dossier) et réutilisé pour
 * tous les callbacks relatifs au même dossier.
 */
final class BpmSignalTargetUrlBuilder
{
    public function __construct(
        private readonly ?string      $bpmBaseUrl,
        private readonly string       $signalSecret,
        private readonly string       $caseType,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function build(string $caseId): string
    {
        $token = hash_hmac('sha256', $this->caseType . '.' . $caseId, $this->signalSecret);

        return rtrim($this->resolveBaseUrl(), '/') . '/esup-signature/signal'
            . '?caseType=' . urlencode($this->caseType)
            . '&caseId='   . urlencode($caseId)
            . '&token='    . $token;
    }

    private function resolveBaseUrl(): string
    {
        if ($this->bpmBaseUrl !== null && $this->bpmBaseUrl !== '') {
            return $this->bpmBaseUrl;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new \LogicException(
                'BPM_Open Demat_BASE_URL is not set and no current request is available to derive the base URL.'
            );
        }

        return $request->getSchemeAndHttpHost() . $request->getBaseUrl();
    }
}
