<?php

/**
 * Open Demat Core – PDF Options
 *
 * Cette classe représente les options de configuration utilisées
 * lors de la génération de documents PDF dans la plateforme Core Open Demat.
 * Elle permet de définir les paramètres de rendu appliqués par
 * les moteurs de génération de PDF (par exemple Dompdf).
 *
 * Les options incluent notamment le format du papier, l’orientation
 * du document, l’URL de base pour le chargement des ressources
 * externes et la police par défaut utilisée dans le rendu.
 *
 * Cette structure permet de centraliser les paramètres de génération
 * et de les transmettre facilement aux différents moteurs PDF
 * implémentant `PdfEngineInterface`.
 *
 * Maintenu par les contributeurs Open Demat.
 */

declare(strict_types=1);

namespace OpenDemat\Core\Pdf;

final class PdfOptions
{
    public function __construct(
        public readonly string $paper = 'A4',
        public readonly string $orientation = 'portrait', // portrait|landscape
        public readonly ?string $baseUrl = null,          // pour assets/images absolus
        public readonly string $defaultFont = 'DejaVu Sans',
    ) {}
}
