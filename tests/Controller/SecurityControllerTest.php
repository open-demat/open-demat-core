<?php

namespace OpenDemat\Core\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function test_login_displays_cas_and_shibboleth_choices(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Open Demat');
        $this->assertSelectorTextContains('body', 'Connexion CAS');
        $this->assertSelectorTextContains('body', 'Connexion Shibboleth');
    }

    public function test_llogout_redirects_to_env_logout_url(): void
    {
        $client = static::createClient();
        $client->request('GET', '/llogout');

        $this->assertResponseRedirects('https://cas.test/logout', 302);
    }
}
