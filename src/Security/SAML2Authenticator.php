<?php

declare(strict_types=1);

namespace OpenDemat\Core\Security;

use OpenDemat\Core\Entity\User;
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

final class SAML2Authenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    /**
     * @param array<string, string> $attributeMap
     */
    public function __construct(
        private readonly Saml2AuthFactory $authFactory,
        private readonly UserProvisioner $userProvisioner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly bool $enabled,
        private readonly array $attributeMap,
        private readonly bool $autoCreateUser,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->enabled
            && $request->attributes->get('_route') === 'saml2_acs'
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $auth = $this->authFactory->createAuth();
        $requestId = $request->getSession()->get('saml2_request_id');

        try {
            $auth->processResponse(is_string($requestId) ? $requestId : null);
        } catch (\Throwable $exception) {
            throw new AuthenticationException('Reponse SAML2 invalide : ' . $exception->getMessage(), 0, $exception);
        }

        $errors = $auth->getErrors();
        if ($errors !== []) {
            throw new AuthenticationException('Reponse SAML2 invalide : ' . implode(', ', $errors));
        }

        if (!$auth->isAuthenticated()) {
            throw new AuthenticationException('Authentification SAML2 refusee.');
        }

        $attributes = $auth->getAttributes();
        $identifier = $this->attributeValue($attributes, $this->attributeMap['identifier'] ?? '') ?? $auth->getNameId();

        if (!is_string($identifier) || trim($identifier) === '') {
            throw new AuthenticationException('Identifiant SAML2 introuvable.');
        }

        $request->getSession()->remove('saml2_request_id');

        return new SelfValidatingPassport(new UserBadge($identifier, function () use ($attributes, $identifier): User {
            if (!$this->autoCreateUser) {
                throw new AuthenticationException('Creation automatique des utilisateurs SAML2 desactivee.');
            }

            return $this->userProvisioner->provision(
                username: $identifier,
                email: $this->attributeValue($attributes, $this->attributeMap['email'] ?? ''),
                firstName: $this->attributeValue($attributes, $this->attributeMap['first_name'] ?? ''),
                lastName: $this->attributeValue($attributes, $this->attributeMap['last_name'] ?? ''),
                roles: ['ROLE_USER'],
            );
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $relayState = (string) $request->request->get('RelayState', '');
        if ($relayState !== '') {
            return new RedirectResponse($relayState);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('danger', $exception->getMessage());

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    /**
     * @param array<string, array<int, mixed>> $attributes
     */
    private function attributeValue(array $attributes, string $name): ?string
    {
        if ($name === '' || !isset($attributes[$name]) || $attributes[$name] === []) {
            return null;
        }

        $value = reset($attributes[$name]);

        return is_scalar($value) ? trim((string) $value) : null;
    }
}
