<?php

/**
 * Open Demat Core – Workflow Audit Subscriber
 *
 * Ce subscriber Symfony permet de journaliser automatiquement les
 * transitions de workflow exécutées dans les processus métier de la
 * plateforme Open Demat. Il intercepte les événements de transition des
 * workflows Symfony et enregistre les informations correspondantes
 * dans le système d’audit du Core.
 *
 * Chaque transition effectuée sur un objet métier est ainsi tracée
 * avec le nom du workflow, la transition réalisée, l’état précédent
 * et le nouvel état, ainsi que l’entité concernée.
 *
 * Ce mécanisme permet d’assurer la traçabilité des changements d’état
 * dans les différents processus applicatifs.
 *
 * Maintenu par les contributeurs Open Demat.
 */

namespace OpenDemat\Core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use OpenDemat\Core\AuditLog\AuditLoggerInterface;

class WorkflowAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuditLoggerInterface $auditLogger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.completed' => 'onWorkflowCompleted',
        ];
    }

    public function onWorkflowCompleted(CompletedEvent $event): void
    {
        $subject = $event->getSubject();
        $transition = $event->getTransition();
        $workflowName = $event->getWorkflowName();

        $froms = $transition->getFroms();
        $tos = $transition->getTos();

        $oldState = $froms[0] ?? implode(', ', $froms);
        $newState = $tos[0] ?? implode(', ', $tos);

        $this->auditLogger->log(
            processName: $workflowName,
            action: 'workflow.transition',
            entity: is_object($subject) ? $subject : null,
            context: [
                'workflow' => $workflowName,
                'transition' => $transition->getName(),
                'froms' => $froms,
                'tos' => $tos,
            ],
            category: 'workflow',
            message: sprintf(
                'Transition "%s" exécutée sur le workflow "%s".',
                $transition->getName(),
                $workflowName
            ),
            oldState: is_string($oldState) ? $oldState : null,
            newState: is_string($newState) ? $newState : null,
            flush: true,
        );
    }
}