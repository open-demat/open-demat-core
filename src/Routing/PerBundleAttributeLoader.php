<?php

/**
 * Open Demat Core – Per Bundle Attribute Route Loader
 *
 * Ce loader Symfony permet de découvrir automatiquement les routes
 * déclarées par attributs PHP dans les contrôleurs des bundles Open Demat.
 *
 * Il parcourt les dossiers de contrôleurs présents dans le répertoire
 * app_open_demat/*\/src/Controller et importe leurs routes en utilisant
 * le système d’attributs de Symfony.
 *
 * Pour chaque bundle, le loader tente de détecter automatiquement
 * la namespace racine des contrôleurs afin de configurer correctement
 * l’import des routes.
 *
 * Ce mécanisme permet d’éviter de déclarer manuellement les routes
 * de chaque bundle dans la configuration Symfony et facilite
 * l’intégration de nouveaux modules applicatifs dans la plateforme.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

final class PerBundleAttributeLoader extends Loader
{
    private bool $isLoaded = false;

    public function __construct(private readonly string $projectDir)
    {
    }

    public function load($resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "per_bundle_attributes" loader twice');
        }

        $routes  = new RouteCollection();
        $pattern = $this->projectDir . '/app_open_demat/*/src/Controller';

        foreach (glob($pattern, GLOB_ONLYDIR) as $controllersDir) {
            $ns = $this->guessControllerNamespace($controllersDir);
            if (!$ns) {
                // Pas de namespace détectée => on ignore ce dossier
                continue;
            }

            // On importe les routes par attributs en forçant path + namespace
            $imported = $this->import(
                ['path' => $controllersDir, 'namespace' => $ns],
                'attribute'
            );

            $routes->addCollection($imported);
        }

        $this->isLoaded = true;
        return $routes;
    }

    public function supports($resource, ?string $type = null): bool
    {
        return $type === 'per_bundle_attributes';
    }

    /**
     * Détecte la namespace "racine" des contrôleurs à partir d’un fichier PHP.
     * Exemple: "OpenDemat\AdminBundle\Controller" (même si le fichier est dans un sous-dossier).
     */
    private function guessControllerNamespace(string $controllersDir): ?string
    {
        // Cherche un .php (en profondeur si besoin)
        $candidate = null;

        foreach (glob($controllersDir . '/*.php') as $f) {
            $candidate = $f;
            break;
        }

        if (!$candidate) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($controllersDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $candidate = $file->getPathname();
                    break;
                }
            }
        }

        if (!$candidate || !is_readable($candidate)) {
            return null;
        }

        $src = file_get_contents($candidate);
        if (!preg_match('/^namespace\s+([^;]+);/m', $src, $m)) {
            return null;
        }

        // Si le fichier est "OpenDemat\AdminBundle\Controller\Sub\Xxx",
        // on remonte jusqu’à "...Controller"
        $ns    = trim($m[1]);
        $parts = explode('\\', $ns);
        $idx   = array_search('Controller', $parts, true);

        if ($idx !== false) {
            $ns = implode('\\', array_slice($parts, 0, $idx + 1));
        }

        return $ns;
    }
}
