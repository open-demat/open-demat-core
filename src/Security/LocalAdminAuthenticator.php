<?php

declare(strict_types=1);

namespace OpenDemat\Core\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class LocalAdminAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly UserProvisioner $userProvisioner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $adminUsername,
        private readonly string $adminPassword,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->attributes->get('_route') === 'app_login'
            && $request->request->get('auth_method') === 'local';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $username = trim((string) $request->request->get('_username', ''));
        $password = (string) $request->request->get('_password', '');

        if ($username === '' || $password === '') {
            throw new AuthenticationException('Identifiant ou mot de passe local manquant.');
        }

        if (!hash_equals($this->adminUsername, $username) || !hash_equals($this->adminPassword, $password)) {
            throw new AuthenticationException('Identifiants locaux invalides.');
        }

        $request->getSession()->set('_security.last_username', $username);

        return new SelfValidatingPassport(new UserBadge($username, function () use ($username, $password) {
            return $this->userProvisioner->provision(
                username: $username,
                email: $username . '@local.open-demat',
                firstName: 'Admin',
                lastName: 'Open Demat',
                roles: ['ROLE_ADMIN'],
                plainPassword: $password,
            );
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetPath = (string) $request->request->get('_target_path', '');
        if ($targetPath !== '') {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('danger', $exception->getMessage());

        return new RedirectResponse($this->urlGenerator->generate('app_login', [
            '_target_path' => (string) $request->request->get('_target_path', '/'),
        ]));
    }
}
