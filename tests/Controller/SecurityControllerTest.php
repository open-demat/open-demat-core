<?php

namespace OpenDemat\Core\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function test_llogout_redirects_to_env_logout_url(): void
    {
        $client = static::createClient();
        $client->request('GET', '/llogout');

        $this->assertResponseRedirects('https://cas.test/logout', 302);
    }
}