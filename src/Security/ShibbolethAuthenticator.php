<?php

declare(strict_types=1);

namespace OpenDemat\Core\Security;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class ShibbolethAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $enabled,
        private readonly string $identifierAttribute,
        private readonly string $emailAttribute,
        private readonly string $firstNameAttribute,
        private readonly string $lastNameAttribute,
        private readonly string $defaultEmailDomain,
        private readonly bool $autoCreateUser,
    ) {}

    public function supports(Request $request): bool
    {
        return $this->enabled && null !== $this->getAttribute($request, $this->identifierAttribute);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $identifier = $this->getAttribute($request, $this->identifierAttribute);

        if (null === $identifier) {
            throw new AuthenticationException('Identifiant Shibboleth introuvable.');
        }

        return new SelfValidatingPassport(new UserBadge($identifier, function () use ($request, $identifier): User {
            $userRepository = $this->entityManager->getRepository(User::class);
            $user = $userRepository->findOneBy(['username' => $identifier]);

            if ($user instanceof User) {
                return $user;
            }

            if (!$this->autoCreateUser) {
                throw new AuthenticationException(sprintf('Aucun utilisateur avec le username : %s', $identifier));
            }

            $user = new User();
            $user->setUsername($identifier);
            $user->setEmail($this->getAttribute($request, $this->emailAttribute) ?? $this->buildFallbackEmail($identifier));

            $firstName = $this->getAttribute($request, $this->firstNameAttribute);
            $lastName = $this->getAttribute($request, $this->lastNameAttribute);

            if (null === $firstName || null === $lastName) {
                [$parsedFirstName, $parsedLastName] = $this->parseNameFromIdentifier($identifier);
                $firstName ??= $parsedFirstName;
                $lastName ??= $parsedLastName;
            }

            $user->setPrenom($firstName);
            $user->setNom($lastName);
            $user->setPassword(bin2hex(random_bytes(20)));
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        if (null !== $targetPath) {
            return new RedirectResponse($targetPath);
        }

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response($exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new Response('Authentication required by Shibboleth.', Response::HTTP_UNAUTHORIZED);
    }

    private function getAttribute(Request $request, string $attributeName): ?string
    {
        $value = $request->server->get($attributeName) ?? $request->headers->get($attributeName);

        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function buildFallbackEmail(string $identifier): string
    {
        if (str_contains($identifier, '@')) {
            return $identifier;
        }

        return sprintf('%s@%s', $identifier, $this->defaultEmailDomain);
    }

    private function parseNameFromIdentifier(string $identifier): array
    {
        $identifier = preg_replace('/@.+$/', '', trim($identifier)) ?? $identifier;
        $identifier = str_replace(['.', '_'], ' ', $identifier);
        $identifier = preg_replace('/\s+/', ' ', $identifier) ?? $identifier;
        $parts = array_values(array_filter(explode(' ', $identifier), static fn (string $part): bool => '' !== $part));

        if (count($parts) < 2) {
            $name = $this->prettyName($identifier);

            return [$name, $name];
        }

        $lastName = array_pop($parts);

        return [$this->prettyName(implode(' ', $parts)), $this->prettyName($lastName)];
    }

    private function prettyName(string $value): string
    {
        return mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8');
    }
}
