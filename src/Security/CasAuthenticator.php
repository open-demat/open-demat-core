<?php

/**
 * Open Demat Core – CAS Authenticator
 *
 * Cet authenticator Symfony gère l’authentification des utilisateurs
 * via le protocole CAS dans la plateforme Core Open Demat.
 *
 * Il s’appuie sur le bundle CAS utilisé par l’application pour
 * valider les tickets d’authentification, récupérer l’identité
 * de l’utilisateur et charger ou créer automatiquement son compte
 * local dans la base de données si nécessaire.
 *
 * Ce mécanisme permet d’intégrer l’authentification centralisée
 * de l’Open Demat tout en conservant une gestion locale des utilisateurs,
 * de leurs rôles et de certaines informations complémentaires
 * comme l’email, le prénom et le nom.
 *
 * L’authenticator gère également les redirections après connexion,
 * les cas d’échec d’authentification ainsi que le point d’entrée
 * de sécurité pour les requêtes nécessitant une authentification.
 *
 * Maintenu par les contributeurs Open Demat.
 */

declare(strict_types=1);

namespace OpenDemat\Core\Security;

use OpenDemat\Core\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EcPhp\CasBundle\Security\Core\User\CasUserProviderInterface;
use EcPhp\CasLib\Contract\CasInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
use Throwable;

use function sprintf;

final class CasAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly CasInterface $cas,
        private readonly CasUserProviderInterface $userProvider,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $enabled,
    ) {}

    public function authenticate(Request $request): SelfValidatingPassport
    {
        try {
            $response = $this->cas->requestTicketValidation($this->toPsrRequest($request));
        } catch (Throwable $e) {
            throw new AuthenticationException(
                sprintf('Unable to authenticate the user with such service ticket, %s', $e->getMessage()),
                0,
                $e
            );
        }

        $symfonyResponse = $this->httpFoundationFactory->createResponse($response);
        $casUser = $this->userProvider->loadUserByResponse($symfonyResponse);

        $identifier = $casUser->getUserIdentifier();

        // Récupération éventuelle des attributs CAS (pour l'email notamment)
        $attrs = method_exists($casUser, 'getAttributes') ? (array) $casUser->getAttributes() : [];
        $emailFromCas = $attrs['mail'] ?? $attrs['email'] ?? $attrs['mailPrimaryAddress'] ?? null;

        // 👨‍🎓 Cas étudiant (u + 7+ chiffres)
        if (preg_match('/^u\d{7,}$/', $identifier)) {
            // $user = $this->entityManager
            //     ->getRepository(EtudiantRemboursement::class)
            //     ->findOneBy(['username' => $identifier]);

            // if (!$user) {
            //     $user = new EtudiantRemboursement();
            //     $user->setUsername($identifier);
            //     // si ton entité a un email, décommente :
            //     // if ($emailFromCas) { $user->setEmail($emailFromCas); }
            //     $this->entityManager->persist($user);
            //     $this->entityManager->flush();
            // }
        } else {
            // 👨‍💼 Cas personnel -> créer automatiquement si absent + ROLE_USER
            $userRepo = $this->entityManager->getRepository(User::class);
            $user = $userRepo->findOneBy(['username' => $identifier]);

            if (!$user) {
                $user = new User();
                $user->setUsername($identifier);

                // Email depuis CAS si dispo, sinon fallback raisonnable
                $user->setEmail($emailFromCas ?: ($identifier . '@example.org'));

                // ✅ Déduire prénom/nom depuis "prenom.nom"
                [$prenom, $nom] = $this->parsePrenomNomFromIdentifier($identifier);

                // ⚠️ adapte les setters à ton entité (setPrenom/setNom ou setFirstName/setLastName)
                if (method_exists($user, 'setPrenom')) {
                    $user->setPrenom($prenom);
                }
                if (method_exists($user, 'setNom')) {
                    $user->setNom($nom);
                }

                // Mot de passe aléatoire (non utilisé par CAS, mais champ souvent non-nullable)
                $user->setPassword(bin2hex(random_bytes(20)));

                $user->setRoles(['ROLE_USER']);

                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }
        }

        return new SelfValidatingPassport(
            new UserBadge($identifier, fn() => $user)
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if (false === $request->query->has('ticket')) {
            return null;
        }

        $request->query->remove('ticket');
        $request->query->set('renew', 'true');
        $request->overrideGlobals();

        return new RedirectResponse($request->getUri());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($request->isXmlHttpRequest()) {
            return null;
        }

        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        if ($targetPath) {
            // Si la target renvoie vers l’URL CAS locale, on ignore
            if (str_contains($targetPath, '/cas/login')) {
                return new RedirectResponse('/');
            }

            return new RedirectResponse($targetPath);
        }

        // Nettoyage des params CAS
        $request->query->remove('ticket');
        $request->query->remove('renew');
        $request->overrideGlobals();

        return new RedirectResponse('/');
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['message' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        // Pas de redirection CAS forcée ici
        return new RedirectResponse('/cas/login');
    }

    public function supports(Request $request): bool
    {
        return $this->enabled && $this->cas->supportAuthentication($this->toPsrRequest($request));
    }

    private function toPsrRequest(Request $request): ServerRequestInterface
    {
        return $this->httpMessageFactory->createRequest($request);
    }

    private function parsePrenomNomFromIdentifier(string $identifier): array
    {
        $identifier = trim($identifier);

        // Si pas au format prenom.nom, on fallback “best effort”
        if (!str_contains($identifier, '.')) {
            return [$this->prettyName($identifier), $this->prettyName($identifier)];
        }

        $parts = array_values(array_filter(explode('.', $identifier), fn($p) => $p !== ''));

        // prenom = tout sauf le dernier morceau, nom = dernier morceau
        $nomRaw = array_pop($parts);
        $prenomRaw = implode(' ', $parts); // gère "marie.claire.durand" => "marie claire" / "durand"

        return [$this->prettyName($prenomRaw), $this->prettyName($nomRaw)];
    }

    private function prettyName(string $s): string
    {
        $s = trim($s);
        $s = str_replace(['.', '_'], ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        // On garde les tirets et apostrophes, et on met en "Title Case"
        // "jean-pierre" => "Jean-Pierre", "d'arc" => "D'Arc"
        $s = mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');

        return $s;
    }
}
