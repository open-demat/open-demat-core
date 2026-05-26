<?php

namespace OpenDemat\Core\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IndexControllerTest extends WebTestCase
{
    public function test_home_redirects_to_admin_accueil(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseRedirects('https://admin.test/accueil', 302);
    }

    public function test_cas_route_redirects_to_caslogin_when_not_authenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cas');

        // /cas -> redirectToRoute('caslogin', _target_path=/cas)
        $this->assertResponseRedirects('/cas/login?_target_path=/cas', 302);
    }
}