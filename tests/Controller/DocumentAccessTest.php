<?php

namespace OpenDemat\Core\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use OpenDemat\Core\Controller\DocumentController;
use OpenDemat\Core\Entity\Document;
use OpenDemat\Core\Entity\User;
use OpenDemat\Core\Service\MinioStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class DocumentAccessTest extends TestCase
{
    public function testArbitraryStorageKeysAreRejectedBeforeReadingStorage(): void
    {
        $storage = $this->createMock(MinioStorage::class);
        $storage->expects(self::never())->method('getObject');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('find');
        $controller = new DocumentController($storage, $em);
        $this->expectException(NotFoundHttpException::class);
        $controller->proxy('another-bundle/private-document.pdf');
    }

    public function testAnotherUsersDocumentIsRejectedBeforeReadingStorage(): void
    {
        $owner = $this->user(1);
        $document = new Document('private.pdf', 'application/pdf', 1, 'documents', uploadedBy: $owner);
        $storage = $this->createMock(MinioStorage::class);
        $storage->expects(self::never())->method('getObject');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($document);
        $controller = new DocumentController($storage, $em);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($this->user(2), 'main', ['ROLE_USER']));
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);
        $container = new Container();
        $container->set('security.token_storage', $tokens);
        $container->set('security.authorization_checker', $checker);
        $controller->setContainer($container);

        $this->expectException(AccessDeniedException::class);
        $controller->proxy('documents/'.$document->getId()->toRfc4122());
    }

    private function user(int $id): User
    {
        $user = (new User())->setUsername('user'.$id);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);
        return $user;
    }
}
