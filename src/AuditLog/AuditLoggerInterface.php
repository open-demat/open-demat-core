<?php

/**
 * Open Demat Core – Audit Logger Interface
 *
 * Cette interface définit le contrat du service de journalisation
 * utilisé dans les applications du Core Open Demat.
 *
 * Elle expose la méthode `log()` permettant d’enregistrer un événement
 * métier ou technique dans le système d’audit (action utilisateur,
 * changement d’état, opération sur une entité, etc.).
 *
 * Les implémentations de cette interface doivent gérer la persistance
 * des informations de traçabilité telles que :
 * - le processus concerné
 * - l’action effectuée
 * - l’entité impactée
 * - le contexte et les messages associés
 * - les transitions d’état éventuelles
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\AuditLog;

interface AuditLoggerInterface
{
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
    ): void;
}