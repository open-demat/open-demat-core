<?php

namespace OpenDemat\Core\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function test_login_displays_enabled_authentication_choices(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Open Demat');
        $this->assertSelectorTextContains('body', 'Connexion locale');
        $this->assertSelectorTextContains('body', 'Connexion CAS');
        $this->assertSelectorTextContains('body', 'Connexion SAML2');
    }

    public function test_llogout_redirects_to_env_logout_url(): void
    {
        $client = static::createClient();
        $client->request('GET', '/llogout');

        $this->assertResponseRedirects('https://cas.test/logout', 302);
    }

    public function test_saml2_metadata_returns_sp_xml(): void
    {
        $client = static::createClient();
        $client->request('GET', '/saml2/metadata');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/samlmetadata+xml');
        $this->assertStringContainsString('EntityDescriptor', $client->getResponse()->getContent() ?: '');
        $this->assertStringContainsString('AssertionConsumerService', $client->getResponse()->getContent() ?: '');
    }
}
