<?php

namespace OpenDemat\Core\Tests\Helpers;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Entity\User;

final class TestUserFactory
{
    public static function createUser(EntityManagerInterface $em, string $username, array $roles = ['ROLE_USER']): User
    {
        $u = new User();

        // Adapte si ton entity a d'autres champs obligatoires
        $u->setUsername($username);
        $u->setEmail($username.'@test.local');
        $u->setRoles($roles);

        $em->persist($u);
        $em->flush();

        return $u;
    }
}