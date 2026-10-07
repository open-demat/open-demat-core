<?php

declare(strict_types=1);

namespace OpenDemat\Core\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use OpenDemat\Core\Controller\MessageController;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Mailer\Message\TemplatedMailMessage;
use OpenDemat\Core\Mailer\Service\MailerService;
use OpenDemat\Core\Notification\BundleNotificationPreference;
use OpenDemat\Core\Notification\InboxService;

final class InboxTest extends TestCase
{
    private Connection $connection;
    private InboxService $inbox;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE "user" (id INTEGER PRIMARY KEY, email TEXT)');
        $this->connection->executeStatement('CREATE TABLE inbox_message (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, subject TEXT, body TEXT, bundle_key TEXT, created_at TEXT, read_at TEXT)');
        $this->connection->insert('user', ['id' => 1, 'email' => 'Alice@Example.org']);
        $this->connection->insert('user', ['id' => 2, 'email' => 'bob@example.org']);
        $twig = new Environment(new ArrayLoader([
            'mail.html.twig' => '<html><body><p>{{ content }}</p><a href="https://example.org/dossier">Ouvrir</a></body></html>',
        ]));
        $this->inbox = new InboxService($this->connection, new BodyRenderer($twig));
    }

    public function testMutedNotificationStillStoresEscapedContentInInbox(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $preferences = $this->createStub(BundleNotificationPreference::class);
        $preferences->method('isMutedForEmail')->willReturn(true);
        $mailer = new MailerService($bus, $this->createStub(EntityManagerInterface::class), $this->connection, $preferences, $this->inbox, new NullLogger());
        $mailer->sendBundleNotification('courriers', 'alice@example.org', 'Dossier important', 'mail.html.twig', ['content' => '<script>alert(1)</script>']);

        $messages = $this->inbox->page(1, 1);
        self::assertCount(1, $messages);
        self::assertSame('COURRIERS', $messages[0]['bundle_key']);
        self::assertSame(1, $this->inbox->unreadCount(1));
        self::assertSame(0, $this->inbox->unreadCount(2));
        $body = $this->inbox->findForUser((int) $messages[0]['id'], 1)['body'];
        self::assertStringContainsString('&lt;script&gt;', $body);
    }

    public function testDispatchFailureDoesNotLoseStoredMessage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willThrowException(new \RuntimeException('Transport unavailable'));
        $mailer = new MailerService($bus, $this->createStub(EntityManagerInterface::class), $this->connection, $this->createStub(BundleNotificationPreference::class), $this->inbox, new NullLogger());
        try {
            $mailer->sendTemplated('alice@example.org', 'Important', 'mail.html.twig', ['content' => 'Dossier']);
            self::fail('Dispatch should fail');
        } catch (\RuntimeException $exception) {
            self::assertSame('Transport unavailable', $exception->getMessage());
        }
        self::assertSame(1, $this->inbox->unreadCount(1));
    }

    public function testArchiveFailureIsLoggedAndEmailStillDispatchedInsideOuterTransaction(): void
    {
        $this->connection->executeStatement('DROP TABLE inbox_message');
        $this->connection->beginTransaction();
        $this->connection->insert('user', ['id' => 99, 'email' => 'outer@example.org']);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            self::assertSame(1, $this->connection->getTransactionNestingLevel());
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM "user" WHERE id = 99'));
            // The connection remains usable by a database-backed Messenger transport.
            $this->connection->insert('user', ['id' => 100, 'email' => 'dispatch@example.org']);
            self::assertSame('alice@example.org', $message->to);

            return new Envelope($message);
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::anything(),
            self::callback(static fn (array $context): bool => $context['exception'] instanceof \Throwable && $context['email_dispatch_enabled'] === true),
        );
        $mailer = new MailerService($bus, $this->createStub(EntityManagerInterface::class), $this->connection, $this->createStub(BundleNotificationPreference::class), $this->inbox, $logger);
        try {
            $mailer->sendTemplated('alice@example.org', 'Important', 'mail.html.twig', ['content' => 'Dossier']);
            self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM "user" WHERE id = 100'));
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testRenderingFailureDoesNotPreventEmailDispatchAttempt(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        $mailer = new MailerService($bus, $this->createStub(EntityManagerInterface::class), $this->connection, $this->createStub(BundleNotificationPreference::class), $this->inbox, new NullLogger());
        $mailer->sendTemplated('alice@example.org', 'Important', 'missing.html.twig');
        self::assertSame(0, $this->connection->getTransactionNestingLevel());
        self::assertSame(0, $this->inbox->unreadCount(1));
    }

    public function testMutedNotificationReportsArchiveFailureWithoutSendingEmail(): void
    {
        $this->connection->executeStatement('DROP TABLE inbox_message');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $preferences = $this->createStub(BundleNotificationPreference::class);
        $preferences->method('isMutedForEmail')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::anything(),
            self::callback(static fn (array $context): bool => $context['email_dispatch_enabled'] === false),
        );
        $mailer = new MailerService($bus, $this->createStub(EntityManagerInterface::class), $this->connection, $preferences, $this->inbox, $logger);
        $this->expectException(\Doctrine\DBAL\Exception::class);
        $mailer->sendBundleNotification('COURRIERS', 'alice@example.org', 'Important', 'mail.html.twig', ['content' => 'Dossier']);
    }

    public function testFailedSecondRecipientInsertionRollsBackAllInboxCopies(): void
    {
        $this->connection->insert('user', ['id' => 3, 'email' => 'alice@example.org']);
        $this->connection->executeStatement("CREATE TRIGGER reject_inbox BEFORE INSERT ON inbox_message WHEN NEW.user_id = 3 BEGIN SELECT RAISE(ABORT, 'Simulated insertion failure'); END");
        try {
            $this->store();
            self::fail('The second insertion must fail');
        } catch (\Doctrine\DBAL\Exception) {
            self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM inbox_message'));
            self::assertSame(0, $this->connection->getTransactionNestingLevel());
        }
    }

    public function testArchiveFailurePreservesOuterTransactionWrites(): void
    {
        $this->connection->insert('user', ['id' => 3, 'email' => 'alice@example.org']);
        $this->connection->executeStatement("CREATE TRIGGER reject_inbox BEFORE INSERT ON inbox_message WHEN NEW.user_id = 3 BEGIN SELECT RAISE(ABORT, 'Simulated insertion failure'); END");
        $this->connection->beginTransaction();
        try {
            $this->connection->insert('user', ['id' => 99, 'email' => 'outer@example.org']);
            try {
                $this->store();
                self::fail('The second insertion must fail');
            } catch (\Doctrine\DBAL\Exception) {
                self::assertSame(1, $this->connection->getTransactionNestingLevel());
                self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM "user" WHERE id = 99'));
                self::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM inbox_message'));
            }
            $this->connection->commit();
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM "user" WHERE id = 99'));
    }

    public function testReadIsIdempotentAndScopedToOwner(): void
    {
        $this->store();
        self::assertFalse($this->inbox->findForUser(1, 2));
        $this->inbox->markRead(1, 2);
        self::assertSame(1, $this->inbox->unreadCount(1));
        $this->inbox->markRead(1, 1);
        $readAt = $this->inbox->findForUser(1, 1)['read_at'];
        $this->inbox->markRead(1, 1);
        self::assertSame($readAt, $this->inbox->findForUser(1, 1)['read_at']);
        self::assertSame(0, $this->inbox->unreadCount(1));
    }

    public function testPaginationHasStableOrderingAndNoOverlap(): void
    {
        for ($index = 0; $index < 30; ++$index) {
            $this->store();
        }
        $first = $this->inbox->page(1, 1);
        $second = $this->inbox->page(1, 2);
        self::assertCount(26, $first);
        self::assertCount(5, $second);
        self::assertSame(30, $first[0]['id']);
        self::assertSame(5, $second[0]['id']);
    }

    public function testExternalRecipientHasNoInboxCopy(): void
    {
        $this->inbox->store(new TemplatedMailMessage('external@example.org', 'Subject', 'unknown.html.twig'));
        self::assertSame(0, $this->inbox->unreadCount(1));
    }

    public function testBodyIsIsolatedAndReadingDoesNotMarkRead(): void
    {
        $this->store();
        $response = $this->controller(1)->body(1, $this->inbox);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('target="_blank"', $response->getContent());
        self::assertStringContainsString('rel="noopener noreferrer"', $response->getContent());
        self::assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        self::assertStringContainsString('sandbox allow-popups', $response->headers->get('Content-Security-Policy'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertSame(1, $this->inbox->unreadCount(1));
    }

    public function testBodyOfAnotherUsersMessageIsNotAccessible(): void
    {
        $this->store();
        $this->expectException(NotFoundHttpException::class);
        $this->controller(2)->body(1, $this->inbox);
    }

    public function testAnonymousCannotReadInbox(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->controller(null)->count($this->inbox);
    }

    public function testMarkReadRejectsInvalidCsrfToken(): void
    {
        $this->store();
        $controller = $this->controller(1);
        try {
            $controller->read(1, new Request(), $this->inbox);
            self::fail('Invalid CSRF should be denied');
        } catch (AccessDeniedException) {
            self::assertSame(1, $this->inbox->unreadCount(1));
        }
    }

    private function store(): void
    {
        $this->inbox->store(new TemplatedMailMessage('alice@example.org', 'Subject', 'mail.html.twig', ['content' => 'Dossier']));
    }

    private function controller(?int $userId): MessageController
    {
        $container = new Container();
        $storage = new TokenStorage();
        if ($userId !== null) {
            $user = (new User())->setUsername('test');
            (new \ReflectionProperty(User::class, 'id'))->setValue($user, $userId);
            $storage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));
        }
        $container->set('security.token_storage', $storage);
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(false);
        $container->set('security.csrf.token_manager', $csrf);
        $controller = new MessageController();
        $controller->setContainer($container);

        return $controller;
    }
}
