<?php

/**
 * Open Demat Core – Hub Application Synchronizer
 *
 * Ce service permet de synchroniser automatiquement les définitions
 * d’applications du Hub avec la base de données du Core Open Demat.
 *
 * Il parcourt les applications déclarées dans le code via des
 * implémentations de `AppDefinitionInterface` et crée ou met à jour
 * les entrées correspondantes dans l’entité `Application`.
 *
 * Cette synchronisation garantit que le portail dispose d’un registre
 * applicatif cohérent entre la configuration du code et les données
 * stockées en base, tout en laissant certains paramètres de pilotage
 * fonctionnel, comme l’activation d’une application, sous le contrôle
 * de la base de données.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Hub;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use OpenDemat\Core\Entity\Application;

final class HubAppSynchronizer
{
    private bool $synced = false;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @param iterable<AppDefinitionInterface> $definitions
     */
    public function sync(iterable $definitions): void
    {
        if ($this->synced) {
            return;
        }
        $this->synced = true;

        $repo = $this->em->getRepository(Application::class);

        /** @var Application[] $all */
        $all = $repo->findAll();

        // Index existants par key
        $existing = [];
        foreach ($all as $app) {
            $existing[$app->getKey()] = $app;
        }

        foreach ($definitions as $def) {
            $key       = $def->getKey();
            $routeName = $def->getRoute();

            // 1) path Symfony (ex: "/admin", "/csst")
            $path = $this->urlGenerator->generate($routeName);

            // 2) base URL à partir de l’ENV (ADMIN_URL, CSST_URL, …)
            $envName = strtoupper($key) . '_URL';
            $base    = $_ENV[$envName] ?? $_SERVER[$envName] ?? '';

            // 3) URL complète qui sera stockée en DB
            $url = $base === ''
                ? $path
                : rtrim($base, '/') . $path;

            $defRoles = $def->getRoles();
            if (is_array($defRoles)) {
                sort($defRoles);
            }

            if (!isset($existing[$key])) {
                // === CAS 1 : nouvelle app → on insère tout ===
                $app = (new Application())
                    ->setKey($key)
                    ->setTitle($def->getTitle())
                    ->setRoute($routeName)
                    ->setIcon($def->getIcon())
                    ->setRoles($defRoles)
                    ->setDescription($def->getDescription())
                    ->setEnabled(true) // uniquement à la création
                    ->setBaseUrl($url);

                $this->em->persist($app);
                continue;
            }

            // === CAS 2 : app déjà présente → on resynchronise les champs gérés par le code ===
            $app = $existing[$key];
            $updated = false;

            if ($app->getTitle() !== $def->getTitle()) {
                $app->setTitle($def->getTitle());
                $updated = true;
            }

            if ($app->getRoute() !== $routeName) {
                $app->setRoute($routeName);
                $updated = true;
            }

            if ($app->getIcon() !== $def->getIcon()) {
                $app->setIcon($def->getIcon());
                $updated = true;
            }

            $appRoles = $app->getRoles();
            if (is_array($appRoles)) {
                $tmp = $appRoles;
                sort($tmp);
                $appRoles = $tmp;
            }

            if ($appRoles !== $defRoles) {
                $app->setRoles($defRoles);
                $updated = true;
            }

            if ($app->getDescription() !== $def->getDescription()) {
                $app->setDescription($def->getDescription());
                $updated = true;
            }

            if ($app->getBaseUrl() !== $url) {
                $app->setBaseUrl($url);
                $updated = true;
            }

            // IMPORTANT : on ne touche pas à enabled ici (DB reste maître)

            if ($updated) {
                $this->em->persist($app);
            }
        }

        $this->em->flush();
    }
}
