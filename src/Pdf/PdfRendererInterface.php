<?php

/**
 * Open Demat Core – PDF Renderer Interface
 *
 * Cette interface définit le contrat des services responsables de la
 * génération de documents PDF dans la plateforme Core Open Demat.
 *
 * Elle expose une méthode permettant de produire un document PDF à
 * partir d’un template (généralement Twig) et d’un contexte de données.
 * Les implémentations concrètes de cette interface s’appuient sur un
 * moteur de conversion HTML → PDF afin de générer le document final.
 *
 * Cette abstraction permet de découpler la logique de génération
 * documentaire du moteur de rendu utilisé et facilite ainsi
 * l’évolution ou le remplacement du moteur PDF dans l’application.
 *
 * Maintenu par les contributeurs Open Demat.
 */

declare(strict_types=1);

namespace OpenDemat\Core\Pdf;

interface PdfRendererInterface
{
    /**
     * @param array<string,mixed> $context
     */
    public function render(string $template, array $context = [], ?PdfOptions $options = null): string;
}
