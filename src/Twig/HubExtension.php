<?php

/**
 * Open Demat Core – Hub Twig Extension
 *
 * Cette extension Twig expose les applications enregistrées
 * dans le Hub du Core Open Demat sous forme de variable globale
 * accessible dans les templates Twig.
 *
 * Elle utilise le `HubRegistry` pour récupérer la liste
 * des applications disponibles dans la plateforme et les
 * rendre accessibles via la variable globale `app_hub_apps`.
 *
 * Cette variable est généralement utilisée dans les layouts
 * ou menus de navigation afin d’afficher dynamiquement les
 * applications disponibles pour l’utilisateur.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use OpenDemat\Core\Hub\HubRegistry;

class HubExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private HubRegistry $registry)
    {
    }

    public function getGlobals(): array
    {
        return [
            'app_hub_apps' => $this->registry->all(),
        ];
    }
}
