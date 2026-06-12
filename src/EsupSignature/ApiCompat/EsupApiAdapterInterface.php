<?php

namespace OpenDemat\Core\EsupSignature\ApiCompat;

use OpenDemat\Core\EsupSignature\DTO\SignType;

interface EsupApiAdapterInterface
{
    /**
     * Normalise la valeur d'un SignType avant envoi HTTP.
     * Certaines versions regroupent plusieurs types sous une valeur canonique.
     */
    public function normalizeSignType(SignType $type): string;

    /**
     * Indique si une fonctionnalité est disponible sur la version du serveur.
     */
    public function supportsFeature(EsupFeature $feature): bool;

    /**
     * Retourne l'identifiant de version de l'adapter (ex: "1.31", "1.36").
     */
    public function getVersionLabel(): string;
}
