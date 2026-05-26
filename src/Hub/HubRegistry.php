<?php

/**
 * Open Demat Core – Hub Registry
 *
 * Ce service constitue le registre central des applications du portail
 * Core Open Demat. Il permet de récupérer la liste des applications disponibles
 * et activées dans la plateforme afin de construire dynamiquement le
 * catalogue des services accessibles aux utilisateurs.
 *
 * Avant de retourner la liste des applications, il déclenche la
 * synchronisation entre les définitions d’applications déclarées dans
 * le code et les enregistrements présents dans la base de données.
 *
 * Ce mécanisme garantit que toutes les applications connues du système
 * sont correctement enregistrées dans le registre applicatif.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Hub;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\Application;

final class HubRegistry
{
    /**
     * @param iterable<AppDefinitionInterface> $apps
     */
    public function __construct(
        private iterable $apps,
        private HubAppSynchronizer $synchronizer,
        private EntityManagerInterface $em,
    ) {}

    public function all(): array
    {
        // S’assure que la DB contient toutes les apps connues du code
        $this->synchronizer->sync($this->apps);

        $repo = $this->em->getRepository(Application::class);
        /** @var Application[] $apps */
        $apps = $repo->findBy(['enabled' => true], ['key' => 'ASC']);

        $out = [];

        foreach ($apps as $app) {
            $out[] = [
                'key'   => $app->getKey(),
                'title' => $app->getTitle(),
                'route' => $app->getRoute(),
                'url'   => $app->getBaseUrl(),
                'icon'  => $app->getIcon(),
                'roles' => $app->getRoles(),
                'desc'  => $app->getDescription(),
            ];
        }

        return $out;
    }
}
