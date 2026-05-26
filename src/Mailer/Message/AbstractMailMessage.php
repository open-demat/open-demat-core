<?php

/**
 * Open Demat Core – Abstract Mail Message
 *
 * Cette classe abstraite représente la structure de base des messages
 * utilisés pour l’envoi d’emails dans la plateforme Core Open Demat.
 *
 * Elle définit les informations minimales nécessaires à l’envoi d’un
 * email, notamment le destinataire et le sujet. Les classes concrètes
 * héritant de cette base peuvent enrichir le message avec des
 * informations supplémentaires telles que le template, le contexte
 * de données ou l’expéditeur.
 *
 * Ces messages sont généralement traités par Symfony Messenger afin
 * de permettre l’envoi d’emails de manière asynchrone via les handlers
 * dédiés.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\Mailer\Message;

abstract class AbstractMailMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
    ) {}
}
