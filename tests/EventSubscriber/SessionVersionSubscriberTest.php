<?php

namespace OpenDemat\Core\Tests\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\EventSubscriber\SessionVersionSubscriber;
use OpenDemat\Core\Repository\UserRepository;

final class SessionVersionSubscriberTest extends TestCase
{
    public function test_logout_bumps_user_session_version_for_other_hosts(): void
    {
        $user = $this->createPersistedUser('logout-version', ['ROLE_USER']);
        $initialVersion = $user->getSessionVersion();
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('find')
            ->with($user->getId())
            ->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $subscriber = new SessionVersionSubscriber(
            $this->createStub(TokenStorageInterface::class),
            new RequestStack(),
            $this->createStub(RouterInterface::class),
            $userRepository,
            $entityManager,
        );

        $subscriber->onLogout(new LogoutEvent(new Request(), $token));

        self::assertSame($initialVersion + 1, $user->getSessionVersion());
    }

    public function test_role_change_bumps_user_session_version_once(): void
    {
        $user = $this->createPersistedUser('role-version', ['ROLE_USER']);
        $initialVersion = $user->getSessionVersion();

        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        self::assertSame($initialVersion + 1, $user->getSessionVersion());

        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        self::assertSame($initialVersion + 1, $user->getSessionVersion());
    }

    /**
     * @param string[] $roles
     */
    private function createPersistedUser(string $username, array $roles): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setRoles($roles);

        $id = new \ReflectionProperty(User::class, 'id');
        $id->setValue($user, 1);

        return $user;
    }
}
