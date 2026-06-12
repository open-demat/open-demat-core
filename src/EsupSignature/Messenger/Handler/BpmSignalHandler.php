<?php

namespace OpenDemat\Core\EsupSignature\Messenger\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use OpenDemat\Core\EsupSignature\DTO\SignRequestStatus;
use OpenDemat\Core\EsupSignature\Entity\SignRequest;
use OpenDemat\Core\EsupSignature\Event\EsupSignatureCompletedEvent;
use OpenDemat\Core\EsupSignature\Messenger\Message\ProcessBpmSignalMessage;

#[AsMessageHandler]
final class BpmSignalHandler
{
    public function __construct(
        private readonly EntityManagerInterface   $em,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface          $logger,
    ) {
    }

    public function __invoke(ProcessBpmSignalMessage $message): void
    {
        $payload   = $message->payload;
        $caseType  = $payload['caseType'] ?? null;
        $caseId    = $payload['caseId']   ?? null;
        $rawStatus = $payload['status']   ?? null;

        /** @var SignRequest|null $signRequest */
        $signRequest = $this->em->getRepository(SignRequest::class)
            ->findOneBy(['caseType' => $caseType, 'caseId' => $caseId]);

        if ($signRequest === null) {
            $this->logger->warning('[BpmSignal] SignRequest introuvable — signal ignoré', [
                'caseType' => $caseType,
                'caseId'   => $caseId,
            ]);
            return;
        }

        if ($signRequest->getStatus()->isTerminal()) {
            $this->logger->warning('[BpmSignal] SignRequest déjà terminée — signal redondant ignoré', [
                'caseType' => $caseType,
                'caseId'   => $caseId,
                'status'   => $signRequest->getStatus()->value,
            ]);
            return;
        }

        $status = $rawStatus ? SignRequestStatus::tryFrom($rawStatus) : null;
        if ($status === null) {
            $this->logger->warning('[BpmSignal] Statut inconnu dans le payload', [
                'caseType'  => $caseType,
                'rawStatus' => $rawStatus,
            ]);
            return;
        }

        $signRequest->setStatus($status);
        $signRequest->setCallbackPayload($payload);
        $this->em->flush();

        $this->logger->info('[BpmSignal] SignRequest mise à jour', [
            'caseType' => $caseType,
            'caseId'   => $caseId,
            'status'   => $status->value,
        ]);

        $this->eventDispatcher->dispatch(
            new EsupSignatureCompletedEvent($signRequest, $status, $payload)
        );
    }
}
