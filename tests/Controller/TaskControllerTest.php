<?php

namespace OpenDemat\Core\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use OpenDemat\Core\Tests\Helpers\TestUserFactory;

final class TaskControllerTest extends WebTestCase
{
    public function test_inbox_count_requires_role_user(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tasks/inbox/count');

        // Avec IsGranted('ROLE_USER') -> 302/401/403 selon firewall
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [302, 401, 403], true));
    }

    public function test_inbox_count_returns_json_when_authenticated(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        $user = TestUserFactory::createUser($em, 'john', ['ROLE_USER']);
        $client->loginUser($user, 'main');

        $client->request('GET', '/tasks/inbox/count');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertJson($client->getResponse()->getContent() ?: '');
    }
}