<?php

/**
 * Open Demat Core – Mailer Service
 *
 * Ce service centralise l’envoi d’emails dans la plateforme Core Open Demat.
 * Il fournit des méthodes permettant d’envoyer des notifications
 * par email à différents types de cibles : un utilisateur, une
 * adresse email directe ou l’ensemble des utilisateurs possédant
 * un rôle spécifique.
 *
 * Les emails sont envoyés sous forme de messages `TemplatedMailMessage`
 * via Symfony Messenger, ce qui permet de traiter l’envoi de manière
 * asynchrone et de ne pas bloquer les requêtes HTTP.
 *
 * Le service permet également de résoudre dynamiquement les destinataires
 * à partir des rôles stockés en base de données.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Mailer\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Mailer\Message\TemplatedMailMessage;

class MailerService
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    ) {}

    /**
     * Cible générique : ROLE_* => tous les users ayant ce rôle (non hérité),
     * sinon email/user => envoi en solo.
     *
     * @param string|User $target "ROLE_FOO" | "mail@domaine.tld" | User
     */
    public function sendToTarget(
        string|User $target,
        string $subject,
        string $template,
        array $context = [],
        ?string $from = null,
    ): void {
        $emails = $this->resolveTargetEmails($target);

        foreach ($emails as $email) {
            $this->dispatchTemplated($email, $subject, $template, $context, $from);
        }
    }

    public function sendTemplated(
        string $to,
        string $subject,
        string $template,
        array $context = [],
        ?string $from = null,
    ): void {
        $this->dispatchTemplated($to, $subject, $template, $context, $from);
    }

    /**
     * Envoie un mail à tous les utilisateurs possédant un rôle donné (non hérité)
     */
    public function sendToRole(
        string $role,
        string $subject,
        string $template,
        array $context = [],
        ?string $from = null,
    ): void {
        $emails = $this->getEmailsForRole($role);

        foreach ($emails as $email) {
            $this->dispatchTemplated($email, $subject, $template, $context, $from);
        }
    }

    // ------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------

    private function dispatchTemplated(
        string $to,
        string $subject,
        string $template,
        array $context,
        ?string $from,
    ): void {
        $to = trim($to);
        if ($to === '') {
            return;
        }

        $this->bus->dispatch(
            new TemplatedMailMessage($to, $subject, $template, $context, $from)
        );
    }

    /**
     * @return string[] unique emails
     */
    private function resolveTargetEmails(string|User $target): array
    {
        // User entity
        if ($target instanceof User) {
            $email = trim((string) $target->getEmail());
            return $email !== '' ? [$email] : [];
        }

        $value = trim($target);
        if ($value === '') {
            return [];
        }

        // Role
        if (str_starts_with($value, 'ROLE_')) {
            return $this->getEmailsForRole($value);
        }

        // Email direct
        if (str_contains($value, '@')) {
            return [$value];
        }

        // Optionnel : si tu veux supporter "id:123" ou username
        // -> à adapter selon ton modèle User
        if (preg_match('/^id:(\d+)$/', $value, $m)) {
            $user = $this->em->find(User::class, (int) $m[1]);
            if ($user instanceof User) {
                $email = trim((string) $user->getEmail());
                return $email !== '' ? [$email] : [];
            }
            return [];
        }

        return [];
    }

    /**
     * @return string[] unique emails
     */
    private function getEmailsForRole(string $role): array
    {
        $sql = <<<SQL
            SELECT DISTINCT email
            FROM "user"
            WHERE roles @> :role
              AND email IS NOT NULL
              AND email <> ''
        SQL;

        return $this->connection->fetchFirstColumn(
            $sql,
            ['role' => json_encode([$role])]
        );
    }
}
