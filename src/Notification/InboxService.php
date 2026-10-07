<?php

declare(strict_types=1);

namespace OpenDemat\Core\Notification;

use Doctrine\DBAL\Connection;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\BodyRendererInterface;
use OpenDemat\Core\Mailer\Message\TemplatedMailMessage;

/** Archive before queueing, within a transaction or savepoint on the caller's connection. */
class InboxService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly BodyRendererInterface $renderer,
    ) {}

    public function store(TemplatedMailMessage $message, ?string $bundleKey = null): void
    {
        // Includes lookup and rendering: an SQL failure must not poison an outer
        // PostgreSQL transaction before MailerService attempts email dispatch.
        $this->connection->transactional(function () use ($message, $bundleKey): void {
            $this->storeWithinTransaction($message, $bundleKey);
        });
    }

    private function storeWithinTransaction(TemplatedMailMessage $message, ?string $bundleKey): void
    {
        $userIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM "user" WHERE LOWER(TRIM(email)) = LOWER(:email)',
            ['email' => trim($message->to)],
        );
        if ($userIds === []) {
            return;
        }

        $email = (new TemplatedEmail())
            ->to($message->to)
            ->subject($message->subject)
            ->htmlTemplate($message->template)
            ->context($message->context);
        if ($message->from !== null) {
            $email->from($message->from);
        }
        $this->renderer->render($email);

        $createdAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($userIds as $userId) {
            $this->connection->insert('inbox_message', [
                'user_id' => $userId,
                'subject' => $message->subject,
                'body' => $email->getHtmlBody() ?? '',
                'bundle_key' => $bundleKey !== null ? strtoupper(trim($bundleKey)) : null,
                'created_at' => $createdAt,
                'read_at' => null,
            ]);
        }
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM inbox_message WHERE user_id = :user AND read_at IS NULL',
            ['user' => $userId],
        );
    }

    public function page(int $userId, int $page): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id, subject, bundle_key, created_at, read_at FROM inbox_message WHERE user_id = :user ORDER BY created_at DESC, id DESC LIMIT 26 OFFSET '.(($page - 1) * 25),
            ['user' => $userId],
        );
    }

    public function findForUser(int $id, int $userId): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT * FROM inbox_message WHERE id = :id AND user_id = :user',
            ['id' => $id, 'user' => $userId],
        );
    }

    public function markRead(int $id, int $userId): void
    {
        $this->connection->executeStatement(
            'UPDATE inbox_message SET read_at = :date WHERE id = :id AND user_id = :user AND read_at IS NULL',
            ['date' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'id' => $id, 'user' => $userId],
        );
    }
}
