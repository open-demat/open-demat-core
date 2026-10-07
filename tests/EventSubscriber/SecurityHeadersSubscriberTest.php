<?php

namespace OpenDemat\Core\Tests\EventSubscriber;

use OpenDemat\Core\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testEmailBodyKeepsItsSandboxPolicyAndPrivateReferrerPolicy(): void
    {
        $policy = "sandbox; default-src 'none'; frame-ancestors 'self'";
        $response = new Response(headers: [
            'Content-Security-Policy' => $policy,
            'Referrer-Policy' => 'no-referrer',
        ]);
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), Request::create('https://example.org/messages/1/body'), HttpKernelInterface::MAIN_REQUEST, $response);
        (new SecurityHeadersSubscriber())->onKernelResponse($event);

        self::assertSame($policy, $response->headers->get('Content-Security-Policy'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
    }

    public function testHttpResponseDoesNotAdvertiseHsts(): void
    {
        $response = new Response();
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), Request::create('http://example.org'), HttpKernelInterface::MAIN_REQUEST, $response);
        (new SecurityHeadersSubscriber())->onKernelResponse($event);

        self::assertSame("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        self::assertFalse($response->headers->has('Strict-Transport-Security'));
    }
}
