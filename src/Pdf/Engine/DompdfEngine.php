<?php

/**
 * Open Demat Core – Dompdf Engine
 *
 * Cette classe implémente un moteur de génération de PDF basé sur la
 * bibliothèque Dompdf. Elle permet de convertir du contenu HTML en
 * document PDF en appliquant des options de rendu définies dans
 * l’objet `PdfOptions`.
 *
 * Le moteur est utilisé par le système de génération de documents
 * du Core afin de produire des fichiers PDF à partir de templates
 * HTML (généralement Twig), par exemple pour des documents
 * administratifs, des attestations ou des exports.
 *
 * Maintenu par les contributeurs Open Demat.
 */

declare(strict_types=1);

namespace OpenDemat\Core\Pdf\Engine;

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use OpenDemat\Core\Pdf\PdfOptions;

final class DompdfEngine implements PdfEngineInterface
{
    public function htmlToPdf(string $html, PdfOptions $options): string
    {
        $dompdfOptions = new DompdfOptions();
        $dompdfOptions->set('isRemoteEnabled', true); // autorise http(s) pour images/css si besoin
        $dompdfOptions->set('defaultFont', $options->defaultFont);

        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->loadHtml($html);

        $dompdf->setPaper($options->paper, $options->orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
