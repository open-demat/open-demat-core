<?php

/**
 * Open Demat Core – Index Controller
 *
 * Ce contrôleur gère les points d’entrée principaux de l’application Core.
 * Il permet notamment de rediriger les utilisateurs vers l’interface
 * d’administration principale et de gérer l’accès via l’authentification CAS.
 *
 * La route racine (`/`) redirige vers l’URL d’administration définie dans
 * la variable d’environnement `ADMIN_URL`, tandis que la route `/cas`
 * vérifie l’authentification de l’utilisateur avant d’effectuer cette
 * redirection.
 *
 * Ce mécanisme permet de centraliser l’accès à la plateforme tout en
 * garantissant que l’utilisateur est authentifié avant d’accéder aux
 * différentes applications du portail.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

class IndexController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        $adminUrl = $_ENV['ADMIN_URL'] ?? null;

        if (!$adminUrl) {
            throw new \RuntimeException("ADMIN_URL n'est pas défini dans le .env");
        }

        return $this->redirect($adminUrl . '/accueil');
    }

    #[Route('/accueil', name: 'app_accueil')]
    public function accueil(Request $request): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login', [
                '_target_path' => $request->getRequestUri(),
            ]);
        }

        return $this->render('index/accueil.html.twig');
    }

    #[Route('/cas', name: 'casIndex')]
    public function indexCas(Request $request): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login', [
                '_target_path' => $request->getRequestUri(),
            ]);
        }

        return $this->forward(self::class.'::index', [
            'request' => $request,
        ]);
    }
}
