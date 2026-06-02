<?php

/**
 * Open Demat Core – Security Controller
 *
 * Ce contrôleur gère les différentes opérations liées à
 * l’authentification des utilisateurs dans les applications
 * basées sur le Core Open Demat.
 *
 * Il assure notamment :
 * - la redirection vers le serveur CAS de l’université pour l’authentification
 * - la gestion du retour du CAS et du forçage de session
 * - la déconnexion locale et la redirection vers la déconnexion CAS
 * - la gestion de la page de connexion et des erreurs d’authentification
 *
 * Les routes définies dans ce contrôleur permettent d’intégrer
 * facilement le mécanisme d’authentification centralisée CAS
 * utilisé par la plateforme Open Demat.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/cas/login', name: 'caslogin')]
    public function loginAction()
    {
        $target = urlencode($this->getParameter('cas_login_target'));
        $url = 'https://' . $this->getParameter('cas_host') . ':' . $this->getParameter('cas_port') . $this->getParameter('cas_path') . '/login?service=';
        return $this->redirect($url . $target . '/cas/force');
    }

    #[Route('/cas/force', name: 'force')]
    public function forceAction()
    {
        if ($this->getParameter('cas_gateway')) {
            if (!isset($_SESSION)) {
                session_start();
            }
            session_destroy();
        }

        return $this->redirectToRoute('casIndex');
    }

    #[Route('/llogout', name: 'llogout')]
    public function logoutAction()
    {
        return $this->redirect($_ENV['CAS_LOGOUT_URL']);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
            'target_path' => $request->query->get('_target_path', '/'),
            'saml2_enabled' => (bool) $this->getParameter('saml2_enabled'),
            'saml2_login_url' => (string) $this->getParameter('saml2_login_url'),
        ]);
    }

    #[Route('/auth-check', name: 'auth-check')]
    public function check()
    {
        throw new \LogicException('This code should never be reached');
    }
}
