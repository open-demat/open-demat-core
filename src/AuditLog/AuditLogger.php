<?php

/**
 * Open Demat Core – Audit Logger
 *
 * Ce fichier contient le service `AuditLogger`, utilisé pour enregistrer
 * les actions réalisées dans les applications de la plateforme Open Demat
 * (actions utilisateur, changements d’état, opérations sur des entités,
 * interactions HTTP, etc.).
 *
 * Il centralise la création des entrées de journalisation (`AuditLog`)
 * et enrichit automatiquement les logs avec des informations de contexte :
 * utilisateur connecté, adresse IP, user-agent, route Symfony,
 * entité concernée et états éventuels du processus.
 *
 * Ce système permet d’assurer la traçabilité fonctionnelle et technique
 * des actions dans les différents modules applicatifs.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\AuditLog;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use OpenDemat\Core\Entity\AuditLog;

class AuditLogger implements AuditLoggerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {}

    public function log(
        string $processName,
        string $action,
        ?object $entity = null,
        array $context = [],
        ?string $category = null,
        ?string $message = null,
        ?string $oldState = null,
        ?string $newState = null,
        bool $flush = true,
    ): void {
        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();

        $audit = new AuditLog();
        $audit
            ->setProcessName($processName)
            ->setAction($action)
            ->setCategory($category)
            ->setMessage($message)
            ->setOldState($oldState)
            ->setNewState($newState)
            ->setContext($context !== [] ? $context : null)
            ->setUserIdentifier($this->extractUserIdentifier($user))
            ->setUserDisplayName($this->extractUserDisplayName($user))
            ->setIp($request?->getClientIp())
            ->setUserAgent($request?->headers->get('User-Agent'))
            ->setRoute($request?->attributes->get('_route'))
            ->setMethod($request?->getMethod())
        ;

        if (null !== $entity) {
            $audit
                ->setEntityType($this->extractEntityType($entity))
                ->setEntityId($this->extractEntityId($entity))
                ->setEntityLabel($this->extractEntityLabel($entity))
            ;
        }

        $this->entityManager->persist($audit);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    private function extractUserIdentifier(?object $user): ?string
    {
        if (null === $user) {
            return null;
        }

        if (method_exists($user, 'getUserIdentifier')) {
            return (string) $user->getUserIdentifier();
        }

        if (method_exists($user, 'getEmail')) {
            return (string) $user->getEmail();
        }

        if (method_exists($user, 'getUsername')) {
            return (string) $user->getUsername();
        }

        if (method_exists($user, '__toString')) {
            return (string) $user;
        }

        return null;
    }

    private function extractUserDisplayName(?object $user): ?string
    {
        if (null === $user) {
            return null;
        }

        if (method_exists($user, 'getDisplayName')) {
            return (string) $user->getDisplayName();
        }

        if (method_exists($user, 'getFullName')) {
            return (string) $user->getFullName();
        }

        if (method_exists($user, 'getNomComplet')) {
            return (string) $user->getNomComplet();
        }

        $firstname = method_exists($user, 'getFirstname') ? (string) $user->getFirstname() : null;
        $lastname = method_exists($user, 'getLastname') ? (string) $user->getLastname() : null;

        $fullName = trim(($firstname ?? '') . ' ' . ($lastname ?? ''));
        if ('' !== $fullName) {
            return $fullName;
        }

        return $this->extractUserIdentifier($user);
    }

    private function extractEntityType(object $entity): string
    {
        $parts = explode('\\', $entity::class);

        return end($parts);
    }

    private function extractEntityId(object $entity): ?string
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();

            return null !== $id ? (string) $id : null;
        }

        return null;
    }

    private function extractEntityLabel(object $entity): ?string
    {
        foreach (['getLibelle', 'getLabel', 'getTitle', 'getNom', 'getName'] as $method) {
            if (method_exists($entity, $method)) {
                $value = $entity->{$method}();

                return null !== $value ? (string) $value : null;
            }
        }

        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();

            return null !== $id ? sprintf('%s #%s', $this->extractEntityType($entity), $id) : null;
        }

        return $this->extractEntityType($entity);
    }
}