<?php

declare(strict_types=1);

namespace OpenDemat\Core\Security;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @param string[] $roles
     * @param array<string, mixed> $attributes
     */
    public function provision(
        string $username,
        ?string $email = null,
        ?string $firstName = null,
        ?string $lastName = null,
        array $roles = ['ROLE_USER'],
        ?string $plainPassword = null,
        array $attributes = [],
    ): User {
        $username = trim($username);
        if ($username === '') {
            throw new \InvalidArgumentException('Username must not be empty.');
        }

        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findOneBy(['username' => $username]);

        if (!$user instanceof User) {
            $user = new User();
            $user->setUsername($username);
            $user->setPassword(bin2hex(random_bytes(24)));
            $this->entityManager->persist($user);
        }

        if ($email !== null && trim($email) !== '') {
            $user->setEmail(trim($email));
        } elseif ($user->getEmail() === null) {
            $user->setEmail($username . '@example.org');
        }

        if ($firstName !== null && trim($firstName) !== '') {
            $user->setPrenom(trim($firstName));
        }

        if ($lastName !== null && trim($lastName) !== '') {
            $user->setNom(trim($lastName));
        }

        if ($user->getPrenom() === null || $user->getNom() === null) {
            [$parsedFirstName, $parsedLastName] = $this->parseNameFromIdentifier($username);
            $user->setPrenom($user->getPrenom() ?? $parsedFirstName);
            $user->setNom($user->getNom() ?? $parsedLastName);
        }

        $mergedRoles = array_values(array_unique(array_merge($user->getRoles(), $roles)));
        $user->setRoles($mergedRoles);

        if ($plainPassword !== null && $plainPassword !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }

        if (isset($attributes['service']) && is_string($attributes['service'])) {
            $user->setService($attributes['service']);
        }

        if (isset($attributes['site']) && is_string($attributes['site'])) {
            $user->setSite($attributes['site']);
        }

        if (isset($attributes['fonction']) && is_string($attributes['fonction'])) {
            $user->setFonction($attributes['fonction']);
        }

        $this->entityManager->flush();

        return $user;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseNameFromIdentifier(string $identifier): array
    {
        $identifier = preg_replace('/@.+$/', '', trim($identifier)) ?? $identifier;
        $identifier = str_replace(['.', '_'], ' ', $identifier);
        $identifier = preg_replace('/\s+/', ' ', $identifier) ?? $identifier;
        $parts = array_values(array_filter(explode(' ', $identifier), static fn (string $part): bool => $part !== ''));

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
