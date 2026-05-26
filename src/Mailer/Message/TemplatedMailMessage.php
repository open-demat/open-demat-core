<?php

/**
 * Open Demat Core – Templated Mail Message
 *
 * Cette classe représente un message d’email basé sur un template Twig
 * dans la plateforme Core Open Demat. Elle étend la classe AbstractMailMessage
 * en ajoutant les informations nécessaires à la génération d’un email
 * dynamique à partir d’un template.
 *
 * Elle permet de définir le template à utiliser, le contexte de données
 * injecté dans ce template ainsi que l’expéditeur optionnel du message.
 * Ces messages sont généralement envoyés via Symfony Messenger puis
 * traités par un handler chargé de générer et d’envoyer l’email.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Mailer\Message;

class TemplatedMailMessage extends AbstractMailMessage
{
    public function __construct(
        string $to,
        string $subject,
        public readonly string $template,
        public readonly array $context = [],
        public readonly ?string $from = null,
    ) {
        parent::__construct($to, $subject);
    }
}
