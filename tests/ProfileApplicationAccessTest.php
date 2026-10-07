<?php

declare(strict_types=1);

namespace OpenDemat\Core\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use OpenDemat\Core\Controller\ProfileController;
use OpenDemat\Core\Entity\Application;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Hub\HubAppSynchronizer;
use OpenDemat\Core\Hub\HubRegistry;

final class ProfileApplicationAccessTest extends TestCase
{
    public function testProfileFiltersApplicationsUsingInheritedRolesAndAnyGrantedRole(): void
    {
        $apps = [
            $this->app('PUBLIC', ['ROLE_USER']),
            $this->app('ADMIN', ['ROLE_ADMIN']),
            $this->app('MULTI', ['ROLE_OTHER', 'ROLE_USER']),
            $this->app('FORBIDDEN', ['ROLE_OTHER']),
            $this->app('NO_ROLES', []),
        ];
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findAll')->willReturn([]);
        $repository->method('findBy')->willReturn($apps);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $registry = new HubRegistry([], new HubAppSynchronizer($em, $this->createStub(UrlGeneratorInterface::class)), $em);

        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken((new User())->setUsername('admin'), 'main', ['ROLE_ADMIN']));
        $checker = new AuthorizationChecker($storage, new AccessDecisionManager([
            new RoleHierarchyVoter(new RoleHierarchy(['ROLE_ADMIN' => ['ROLE_USER']])),
        ]));
        $container = new Container();
        $container->set('security.authorization_checker', $checker);
        $controller = new ProfileController();
        $controller->setContainer($container);

        $allowed = (new \ReflectionMethod(ProfileController::class, 'accessibleApps'))->invoke($controller, $registry);
        self::assertSame(['PUBLIC', 'ADMIN', 'MULTI'], array_column($allowed, 'key'));
    }

    private function app(string $key, array $roles): Application
    {
        return (new Application())->setKey($key)->setTitle($key)->setRoute('app_'.$key)->setRoles($roles);
    }
}
