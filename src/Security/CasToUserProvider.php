<?php

/**
 * Open Demat Core – CAS User Provider
 *
 * Ce provider Symfony permet de charger les utilisateurs de
 * l’application à partir des identifiants fournis par le système
 * d’authentification CAS.
 *
 * Il agit comme une passerelle entre l’identité renvoyée par
 * le serveur CAS et les utilisateurs stockés dans la base
 * de données de l’application.
 *
 * Lorsqu’un utilisateur est authentifié via CAS, ce provider
 * recherche le compte correspondant dans la base locale à
 * partir de son identifiant. Si aucun utilisateur n’est trouvé,
 * une exception est levée afin de signaler que le compte
 * n’existe pas dans l’application.
 *
 * Ce mécanisme permet d’intégrer l’authentification centralisée
 * tout en conservant une gestion locale des utilisateurs,
 * de leurs rôles et de leurs informations métier.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Security;

use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Repository\UserRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use EcPhp\CasBundle\Security\Core\User\CasUserProviderInterface;
use Symfony\Component\HttpFoundation\Response;

class CasToUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Cas des étudiants (username commence par u + chiffres)
        if (preg_match('/^u\d{7,}$/', $identifier)) {

        }

        // Cas des users classiques
        $user = $this->userRepository->findOneBy(['username' => $identifier]);

        if (!$user) {
            throw new UserNotFoundException("Aucun utilisateur avec le username : $identifier");
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return in_array($class, [
            \OpenDemat\Core\Entity\User::class,
            \EcPhp\CasBundle\Security\Core\User\CasUser::class, // ← ajoute ça
        ]);
    }

    public function loadUserByResponse(Response $response): UserInterface
    {
        $content = json_decode($response->getContent(), true);

        if (!isset($content['user'])) {
            throw new \RuntimeException('Utilisateur CAS non trouvé dans la réponse');
        }

        $identifier = $content['user'];

        return $this->loadUserByIdentifier($identifier);
    }
}
