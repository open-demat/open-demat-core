<?php

/**
 * Open Demat Core – Mail Message Handler
 *
 * Ce handler Symfony Messenger est responsable de l’envoi des emails
 * générés par les différentes applications de la plateforme Open Demat.
 *
 * Il traite les messages de type `TemplatedMailMessage` et construit
 * un email à partir d’un template Twig, d’un sujet et d’un contexte
 * de données. L’envoi est ensuite effectué via le composant
 * Symfony Mailer.
 *
 * L’utilisation de Symfony Messenger permet de gérer l’envoi des
 * emails de manière asynchrone afin de ne pas bloquer les requêtes
 * HTTP des utilisateurs.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Mailer\Handler;

use OpenDemat\Core\Mailer\Message\TemplatedMailMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendMailMessageHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $defaultFrom,
    ) {}

    public function __invoke(TemplatedMailMessage $message): void
    {
        $email = (new TemplatedEmail())
            ->to($message->to)
            ->from($message->from ?? $this->defaultFrom)
            ->subject($message->subject)
            ->htmlTemplate($message->template)
            ->context($message->context);

        $this->mailer->send($email);
    }
}
