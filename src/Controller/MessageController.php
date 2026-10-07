<?php

declare(strict_types=1);

namespace OpenDemat\Core\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Notification\InboxService;

#[Route('/messages', name: 'core_messages_')]
final class MessageController extends AbstractController
{
    private function userId(): int
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            throw $this->createAccessDeniedException();
        }

        return $user->getId();
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, InboxService $inbox): Response
    {
        $userId = $this->userId();
        $page = min(1000000, max(1, $request->query->getInt('page', 1)));
        $messages = $inbox->page($userId, $page);

        return $this->render('messages/index.html.twig', [
            'messages' => array_slice($messages, 0, 25),
            'hasNext' => count($messages) > 25,
            'page' => $page,
            'unreadCount' => $inbox->unreadCount($userId),
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/count', name: 'count', methods: ['GET'])]
    public function count(InboxService $inbox): JsonResponse
    {
        return new JsonResponse(['count' => $inbox->unreadCount($this->userId())], headers: ['Cache-Control' => 'private, no-store']);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, InboxService $inbox): Response
    {
        $message = $inbox->findForUser($id, $this->userId());
        if ($message === false) {
            throw $this->createNotFoundException();
        }

        return $this->render('messages/show.html.twig', ['message' => $message], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/{id}/body', name: 'body', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function body(int $id, InboxService $inbox): Response
    {
        $message = $inbox->findForUser($id, $this->userId());
        if ($message === false) {
            throw $this->createNotFoundException();
        }

        $body = $message['body'];
        if (trim($body) !== '') {
            $document = \Dom\HTMLDocument::createFromString($body, LIBXML_NOERROR, 'UTF-8');
            foreach ($document->getElementsByTagName('a') as $link) {
                $link->setAttribute('target', '_blank');
                $link->setAttribute('rel', 'noopener noreferrer');
            }
            $body = $document->saveHtml();
        }

        // Isolate email HTML from the portal, including when opened directly.
        return new Response($body, headers: [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "sandbox allow-popups allow-popups-to-escape-sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src https: http: data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    #[Route('/{id}/read', name: 'read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function read(int $id, Request $request, InboxService $inbox): Response
    {
        $userId = $this->userId();
        if (!$this->isCsrfTokenValid('message_read_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide.');
        }
        if ($inbox->findForUser($id, $userId) === false) {
            throw $this->createNotFoundException();
        }

        $inbox->markRead($id, $userId);

        return $this->redirectToRoute('core_messages_show', ['id' => $id]);
    }
}
