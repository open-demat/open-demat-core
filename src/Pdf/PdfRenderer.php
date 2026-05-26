<?php

/**
 * Open Demat Core – PDF Renderer
 *
 * Ce service permet de générer des documents PDF à partir de templates
 * Twig dans la plateforme Core Open Demat. Il agit comme un point d’entrée
 * unique pour la génération de PDF en combinant le moteur de rendu
 * Twig et un moteur de conversion HTML → PDF implémentant
 * `PdfEngineInterface`.
 *
 * Le renderer transforme d’abord un template Twig en HTML, puis
 * transmet ce contenu au moteur PDF configuré (par exemple Dompdf)
 * afin de produire le document final.
 *
 * Il permet également d’injecter automatiquement une balise `<base>`
 * dans le document HTML afin de garantir la résolution correcte des
 * URLs relatives (images, CSS, assets) lors du rendu PDF.
 *
 * Maintenu par les contributeurs Open Demat.
 */

declare(strict_types=1);

namespace OpenDemat\Core\Pdf;

use Twig\Environment;
use OpenDemat\Core\Pdf\Engine\PdfEngineInterface;

final class PdfRenderer implements PdfRendererInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly PdfEngineInterface $engine,
    ) {}

    public function render(string $template, array $context = [], ?PdfOptions $options = null): string
    {
        $options ??= new PdfOptions();

        $html = $this->twig->render($template, $context);

        // Injecte <base href="..."> si tu veux que Dompdf résolve correctement les URLs relatives
        if ($options->baseUrl) {
            $baseTag = '<base href="' . htmlspecialchars(rtrim($options->baseUrl, '/').'/', ENT_QUOTES) . '">';
            $html = preg_replace('~<head(\s[^>]*)?>~i', '$0'.$baseTag, $html, 1) ?? $html;
        }

        return $this->engine->htmlToPdf($html, $options);
    }
}
