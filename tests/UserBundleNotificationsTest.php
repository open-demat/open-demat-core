<?php

declare(strict_types=1);

namespace OpenDemat\Core\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Entity\UserBundleProfile;
use OpenDemat\Core\Mailer\Message\TemplatedMailMessage;
use OpenDemat\Core\Mailer\Service\MailerService;
use OpenDemat\Core\Notification\BundleNotificationPreference;
use OpenDemat\Core\Notification\InboxService;

final class UserBundleNotificationsTest extends TestCase
{
    public function testProfileNormalizesBundleKeyAndStoresMutedPreference(): void
    {
        $profile = new UserBundleProfile(new User(), ' courriers ');

        self::assertSame('COURRIERS', $profile->getBundleKey());
        self::assertFalse($profile->isNotificationsMuted());

        $profile->setNotificationsMuted(true);

        self::assertTrue($profile->isNotificationsMuted());
    }

    public function testProfileRejectsAnEmptyBundleKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UserBundleProfile(new User(), '  ');
    }

    public static function databaseBooleanValues(): iterable
    {
        yield 'PostgreSQL false string' => ['f', false];
        yield 'PostgreSQL true string' => ['t', true];
        yield 'native false' => [false, false];
        yield 'native true' => [true, true];
    }

    #[DataProvider('databaseBooleanValues')]
    public function testPreferenceNormalizesDatabaseBooleanValues(mixed $databaseValue, bool $expected): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchOne')->willReturn($databaseValue);

        $preferences = new BundleNotificationPreference($connection);

        self::assertSame(
            $expected,
            $preferences->isMutedForEmail('alice@example.org', 'courriers')
        );
    }

    public function testMutedBundleNotificationIsNotDispatched(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $preferences = $this->createMock(BundleNotificationPreference::class);
        $preferences->expects(self::once())
            ->method('isMutedForEmail')
            ->with('alice@example.org', 'COURRIERS')
            ->willReturn(true);

        $mailer = $this->createMailer($bus, $preferences);
        $mailer->sendBundleNotification(
            'COURRIERS',
            'alice@example.org',
            'Suivi du courrier',
            'emails/test.html.twig'
        );
    }

    public function testUnmutedBundleNotificationIsDispatched(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool =>
                $message instanceof TemplatedMailMessage
                && $message->to === 'alice@example.org'
                && $message->subject === 'Suivi du courrier'
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $preferences = $this->createStub(BundleNotificationPreference::class);
        $preferences->method('isMutedForEmail')->willReturn(false);

        $mailer = $this->createMailer($bus, $preferences);
        $mailer->sendBundleNotification(
            'COURRIERS',
            'alice@example.org',
            'Suivi du courrier',
            'emails/test.html.twig'
        );
    }

    public function testRoleNotificationChecksEachRecipientAndArchivesMutedMessages(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchFirstColumn')
            ->willReturn(['alice@example.org', 'bob@example.org']);

        $checked = [];
        $preferences = $this->createMock(BundleNotificationPreference::class);
        $preferences->expects(self::exactly(2))->method('isMutedForEmail')
            ->willReturnCallback(static function (string $email, string $key) use (&$checked): bool {
                self::assertSame('COURRIERS', $key);
                $checked[] = $email;
                return $email === 'alice@example.org';
            });

        $archived = [];
        $inbox = $this->createMock(InboxService::class);
        $inbox->expects(self::exactly(2))->method('store')
            ->willReturnCallback(static function (TemplatedMailMessage $message, ?string $key) use (&$archived): void {
                self::assertSame('COURRIERS', $key);
                $archived[] = $message->to;
            });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(self::callback(static fn (TemplatedMailMessage $message): bool => $message->to === 'bob@example.org'))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $mailer = new MailerService(
            $bus, $this->createStub(EntityManagerInterface::class), $connection,
            $preferences, $inbox, new NullLogger(),
        );
        $mailer->sendBundleNotification('COURRIERS', 'ROLE_COURRIERS_OPERATEUR', 'Nouvelle tâche', 'emails/test.html.twig');

        self::assertSame(['alice@example.org', 'bob@example.org'], $checked);
        self::assertSame($checked, $archived);
    }

    private function createMailer(
        MessageBusInterface $bus,
        BundleNotificationPreference $preferences,
    ): MailerService {
        return new MailerService(
            $bus,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(Connection::class),
            $preferences,
            $this->createStub(InboxService::class),
            new NullLogger(),
        );
    }
}
