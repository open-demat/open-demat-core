<?php

namespace OpenDemat\Core\Tests\Controller;

use OpenDemat\Core\Tests\Helpers\TestUserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MessagingFlowTest extends WebTestCase
{
    public function testProfileAndInboxRenderAndEmailBodyKeepsItsSandboxThroughSymfony(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $user = TestUserFactory::createUser($em, 'messaging', ['ROLE_USER']);
        $em->getConnection()->insert('inbox_message', [
            'user_id' => $user->getId(),
            'subject' => 'Votre dossier avance',
            'body' => '<p>Nouvelle étape</p><a href="https://example.org">Dossier</a>',
            'created_at' => '2026-10-07 10:00:00',
        ]);
        $id = (int) $em->getConnection()->fetchOne('SELECT MAX(id) FROM inbox_message WHERE user_id = :user', ['user' => $user->getId()]);
        $client->loginUser($user);

        $client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Notifications de suivi');

        $client->request('GET', '/messages');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Votre dossier avance');

        $client->request('GET', '/messages/'.$id.'/body');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("sandbox allow-popups", $client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');

        $other = TestUserFactory::createUser(static::getContainer()->get('doctrine')->getManager(), 'messaging_other', ['ROLE_USER']);
        $client->loginUser($other);
        $client->request('GET', '/messages/'.$id.'/body');
        self::assertResponseStatusCodeSame(404);
    }
}
