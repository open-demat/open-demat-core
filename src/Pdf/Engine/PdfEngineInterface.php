<?php

/**
 * Open Demat Core – PDF Engine Interface
 *
 * Cette interface définit le contrat des moteurs de génération
 * de documents PDF utilisés dans le Core Open Demat. Elle permet
 * d’abstraire la conversion de contenu HTML en fichier PDF
 * afin de pouvoir utiliser différentes bibliothèques de rendu
 * sans modifier le reste de l’application.
 *
 * Les implémentations de cette interface (par exemple DompdfEngine)
 * sont responsables de transformer du HTML en contenu PDF binaire
 * en appliquant les options définies dans l’objet `PdfOptions`.
 *
 * Cette abstraction facilite le remplacement ou l’ajout de moteurs
 * PDF dans le système de génération documentaire du Core.
 *
 * Maintenu par les contributeurs Open Demat.
 */

declare(strict_types=1);

namespace OpenDemat\Core\Pdf\Engine;

use OpenDemat\Core\Pdf\PdfOptions;

interface PdfEngineInterface
{
    public function htmlToPdf(string $html, PdfOptions $options): string;
}
