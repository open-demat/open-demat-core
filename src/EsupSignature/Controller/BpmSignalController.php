<?php

namespace OpenDemat\Core\EsupSignature\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use OpenDemat\Core\EsupSignature\Messenger\Message\ProcessBpmSignalMessage;
use OpenDemat\Core\EsupSignature\Security\BpmSignalSecretRegistry;

/**
 * Reçoit les signaux ESUP Signature via targetUrl.
 *
 * ACK 200 immédiat après validation HMAC.
 * Traitement métier délégué en asynchrone via Symfony Messenger.
 */
#[Route('/esup-signature/signal', name: 'esup_signature_bpm_signal', methods: ['GET', 'POST'])]
final class BpmSignalController
{
    public function __construct(
        private readonly BpmSignalSecretRegistry $registry,
        private readonly MessageBusInterface     $bus,
        private readonly LoggerInterface         $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload  = array_merge($request->query->all(), $request->request->all());
        $caseType = $payload['caseType'] ?? null;
        $caseId   = $payload['caseId']   ?? null;
        $token    = $payload['token']    ?? null;

        if ($caseType === null || $caseId === null || $token === null) {
            $this->logger->warning('[BpmSignal] Paramètres manquants', ['ip' => $request->getClientIp()]);
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        $secret = $this->registry->getSecret($caseType);
        if ($secret === null) {
            $this->logger->warning('[BpmSignal] caseType non enregistré', [
                'caseType' => $caseType,
                'ip'       => $request->getClientIp(),
            ]);
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        $expected = hash_hmac('sha256', $caseType . '.' . $caseId, $secret);
        if (!hash_equals($expected, $token)) {
            $this->logger->warning('[BpmSignal] Token HMAC invalide', [
                'caseType' => $caseType,
                'caseId'   => $caseId,
                'ip'       => $request->getClientIp(),
            ]);
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        $this->bus->dispatch(new ProcessBpmSignalMessage($payload));

        $this->logger->info('[BpmSignal] Signal reçu et enfilé', [
            'caseType' => $caseType,
            'caseId'   => $caseId,
            'status'   => $payload['status'] ?? '?',
            'step'     => $payload['step']   ?? '?',
        ]);

        return new Response('OK', Response::HTTP_OK);
    }
}
