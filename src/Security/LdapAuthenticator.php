<?php

declare(strict_types=1);

namespace OpenDemat\Core\Security;

use OpenDemat\Core\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class LdapAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly UserProvisioner $userProvisioner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly bool $enabled,
        private readonly string $connectionString,
        private readonly string $baseDn,
        private readonly string $searchFilter,
        private readonly string $bindDn,
        private readonly string $bindPassword,
        private readonly string $identifierAttribute,
        private readonly string $emailAttribute,
        private readonly string $firstNameAttribute,
        private readonly string $lastNameAttribute,
        private readonly bool $autoCreateUser,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->enabled
            && $request->isMethod('POST')
            && $request->attributes->get('_route') === 'app_login'
            && $request->request->get('auth_method') === 'ldap';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $username = trim((string) $request->request->get('_username', ''));
        $password = (string) $request->request->get('_password', '');

        if ($username === '' || $password === '') {
            throw new AuthenticationException('Identifiant ou mot de passe LDAP manquant.');
        }

        try {
            $ldap = Ldap::create('ext_ldap', ['connection_string' => $this->connectionString]);
            $ldap->bind($this->bindDn !== '' ? $this->bindDn : null, $this->bindPassword !== '' ? $this->bindPassword : null);

            $escapedUsername = $ldap->escape($username, '', \LDAP_ESCAPE_FILTER);
            $filter = str_contains($this->searchFilter, '%s')
                ? sprintf($this->searchFilter, $escapedUsername)
                : sprintf('(&%s(%s=%s))', $this->searchFilter, $this->identifierAttribute, $escapedUsername);

            $entries = $ldap->query($this->baseDn, $filter, ['scope' => QueryInterface::SCOPE_SUB])->execute();
            if (count($entries) < 1) {
                throw new AuthenticationException('Utilisateur LDAP introuvable.');
            }

            /** @var Entry $entry */
            $entry = $entries[0];
            $ldap->bind($entry->getDn(), $password);
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AuthenticationException('Authentification LDAP impossible : ' . $exception->getMessage(), 0, $exception);
        }

        $identifier = $this->firstAttribute($entry, $this->identifierAttribute) ?? $username;

        return new SelfValidatingPassport(new UserBadge($identifier, function () use ($entry, $identifier): User {
            if (!$this->autoCreateUser) {
                throw new AuthenticationException('Creation automatique des utilisateurs LDAP desactivee.');
            }

            return $this->userProvisioner->provision(
                username: $identifier,
                email: $this->firstAttribute($entry, $this->emailAttribute),
                firstName: $this->firstAttribute($entry, $this->firstNameAttribute),
                lastName: $this->firstAttribute($entry, $this->lastNameAttribute),
                roles: ['ROLE_USER'],
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

    private function firstAttribute(Entry $entry, string $attribute): ?string
    {
        if ($attribute === '') {
            return null;
        }

        $values = $entry->getAttribute($attribute, false);
        if ($values === null || $values === []) {
            return null;
        }

        $value = reset($values);

        return is_scalar($value) ? trim((string) $value) : null;
    }
}
