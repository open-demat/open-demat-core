<?php

namespace OpenDemat\Core\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use OpenDemat\Core\Tests\Helpers\TestUserFactory;

final class ProfileControllerTest extends WebTestCase
{
    public function test_ajax_me_is_denied_when_not_authenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile/me/ajax');

        $this->assertResponseRedirects('/cas/login', 302);
    }

    public function test_ajax_me_returns_user_and_documents_when_authenticated(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        $user = TestUserFactory::createUser($em, 'alice', ['ROLE_USER']);
        $client->loginUser($user, 'main');

        $client->request('GET', '/profile/me/ajax');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent() ?: '[]', true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('documents', $data);
        $this->assertSame($user->getId(), $data['user']['id'] ?? null);
    }

    public function test_ajax_get_profile_forbidden_when_id_differs(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        $u1 = TestUserFactory::createUser($em, 'u1', ['ROLE_USER']);
        $u2 = TestUserFactory::createUser($em, 'u2', ['ROLE_USER']);

        $client->loginUser($u1, 'main');

        $client->request('GET', '/profile/'.$u2->getId().'/ajax');
        $this->assertResponseStatusCodeSame(403);
    }
}