<?php

namespace OpenDemat\Core\EsupSignature\Security;

/**
 * Chaque bundle consommateur implémente cette interface pour déclarer
 * son caseType et le secret HMAC partagé avec son serveur ES.
 *
 * Les implémentations sont taguées automatiquement via autoconfigure
 * (tag: open_demat.bpm_signal_provider).
 */
interface BpmSignalProviderInterface
{
    public function getCaseType(): string;

    public function getSignalSecret(): string;
}
