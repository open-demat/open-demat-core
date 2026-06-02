<?php

declare(strict_types=1);

namespace OpenDemat\Core\Controller;

use OpenDemat\Core\Security\Saml2AuthFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class Saml2Controller extends AbstractController
{
    #[Route('/saml2/login', name: 'saml2_login', methods: ['GET'])]
    public function login(Request $request, Saml2AuthFactory $authFactory): Response
    {
        if (!(bool) $this->getParameter('saml2_enabled')) {
            throw $this->createNotFoundException('SAML2 is disabled.');
        }

        $returnTo = (string) $request->query->get('_target_path', '/');
        try {
            $auth = $authFactory->createAuth();
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Configuration SAML2 invalide : ' . $exception->getMessage());

            return $this->redirectToRoute('app_login', ['_target_path' => $returnTo]);
        }

        $url = $auth->login($returnTo, [], false, false, true);
        $request->getSession()->set('saml2_request_id', $auth->getLastRequestID());

        return new RedirectResponse($url);
    }

    #[Route('/saml2/acs', name: 'saml2_acs', methods: ['POST'])]
    public function acs(): void
    {
        throw new \LogicException('This route is handled by the SAML2 authenticator.');
    }

    #[Route('/saml2/metadata', name: 'saml2_metadata', methods: ['GET'])]
    public function metadata(Saml2AuthFactory $authFactory): Response
    {
        return new Response($authFactory->getSPMetadata(), Response::HTTP_OK, ['Content-Type' => 'application/samlmetadata+xml']);
    }
}
