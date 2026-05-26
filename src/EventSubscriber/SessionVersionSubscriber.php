<?php

/**
 * Open Demat Core – Session Version Subscriber
 *
 * Ce subscriber Symfony permet de vérifier la validité de la session
 * d’un utilisateur à chaque requête. Il compare la version de session
 * stockée dans la session HTTP avec celle enregistrée en base de données
 * dans l’entité User.
 *
 * Si la version de session a changé ou si l’utilisateur n’existe plus,
 * la session est automatiquement invalidée et l’utilisateur est redirigé
 * vers la page d’authentification.
 *
 * Ce mécanisme permet notamment de forcer la déconnexion des utilisateurs
 * lorsque leurs droits ou leur compte ont été modifiés, renforçant ainsi
 * la sécurité de la plateforme.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Repository\UserRepository;

class SessionVersionSubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY = '_user_session_version';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly UserRepository $userRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // éviter boucle sur login/logout/assets/debug
        $route = $request->attributes->get('_route');
        if (\in_array($route, [
            'app_login',
            'app_logout',
            '_wdt',
            '_profiler',
        ], true)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $tokenUser = $token->getUser();
        if (!$tokenUser instanceof User) {
            return;
        }

        $session = $request->getSession();
        if (!$session) {
            return;
        }

        $freshUser = $this->userRepository->find($tokenUser->getId());

        // utilisateur supprimé => déconnexion
        if (!$freshUser instanceof User) {
            $this->forceLogout($request, $event);
            return;
        }

        $storedVersion = $session->get(self::SESSION_KEY);

        // première requête : on initialise
        if (null === $storedVersion) {
            $session->set(self::SESSION_KEY, $freshUser->getSessionVersion());
            return;
        }

        // version changée => on déconnecte
        if ((int) $storedVersion !== $freshUser->getSessionVersion()) {
            $this->forceLogout($request, $event);
            return;
        }

        // on resynchronise au cas où
        $session->set(self::SESSION_KEY, $freshUser->getSessionVersion());
    }

    private function forceLogout($request, RequestEvent $event): void
    {
        $this->tokenStorage->setToken(null);

        $session = $request->getSession();
        if ($session) {
            $session->invalidate();
        }

        $event->setResponse(new RedirectResponse(
            $this->router->generate('casIndex')
        ));
    }
}