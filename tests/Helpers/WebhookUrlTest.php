<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\WebhookUrl;
use PHPUnit\Framework\TestCase;

/**
 * Covers the shared webhook notification URL builder used by CIT and MIT.
 *
 * @covers \MercadoPago\Woocommerce\Helpers\WebhookUrl
 */
class WebhookUrlTest extends TestCase
{
    private const GATEWAY = 'WC_WooMercadoPago_Custom_Gateway';

    private function neverResolver(): callable
    {
        return function () {
            $this->fail('api_request_url resolver should not be called in this branch.');
        };
    }

    public function testCustomDomainWithOptionsAppendsWcApiAndSourceNews(): void
    {
        $result = WebhookUrl::build(
            'https://my-store.com',
            'yes',
            $this->neverResolver(),
            'https://my-store.com',
            self::GATEWAY
        );

        $this->assertSame(
            'https://my-store.com?wc-api=' . self::GATEWAY . '&source_news=webhooks',
            $result
        );
    }

    public function testCustomDomainWithoutOptionsReturnsDomainAsIs(): void
    {
        $result = WebhookUrl::build(
            'https://my-store.com',
            'no',
            $this->neverResolver(),
            'https://my-store.com',
            self::GATEWAY
        );

        $this->assertSame('https://my-store.com', $result);
    }

    public function testLocalhostCustomDomainFallsThroughToEmptyWhenNoFallback(): void
    {
        // Custom domain is localhost → first branch skipped; second branch requires
        // empty custom domain, so it also skips → empty string.
        $result = WebhookUrl::build(
            'http://localhost:8080',
            'yes',
            $this->neverResolver(),
            'http://localhost:8080',
            self::GATEWAY
        );

        $this->assertSame('', $result);
    }

    public function testInvalidCustomDomainFallsThroughToEmpty(): void
    {
        $result = WebhookUrl::build(
            'not-a-valid-url',
            'yes',
            $this->neverResolver(),
            'http://localhost',
            self::GATEWAY
        );

        $this->assertSame('', $result);
    }

    public function testNoCustomDomainUsesApiRequestUrlWithSourceNews(): void
    {
        $result = WebhookUrl::build(
            '',
            'no',
            fn() => 'https://production-store.com/wc-api/' . self::GATEWAY,
            'https://production-store.com',
            self::GATEWAY
        );

        $this->assertSame(
            'https://production-store.com/wc-api/' . self::GATEWAY . '?source_news=webhooks',
            $result
        );
    }

    public function testNoCustomDomainWithoutWcApiPathUsesAmpersandJoin(): void
    {
        $result = WebhookUrl::build(
            '',
            'no',
            fn() => 'https://production-store.com/?page_id=10',
            'https://production-store.com',
            self::GATEWAY
        );

        $this->assertSame(
            'https://production-store.com/?page_id=10&source_news=webhooks',
            $result
        );
    }

    public function testLocalhostSiteReturnsEmpty(): void
    {
        $result = WebhookUrl::build(
            '',
            'no',
            $this->neverResolver(),
            'http://localhost:8080',
            self::GATEWAY
        );

        $this->assertSame('', $result);
    }

    public function testCustomDomainResolvesEvenWhenApiResolverIsEmpty(): void
    {
        // Custom-domain branch must not depend on the resolver (e.g. WC() null in WP-Cron):
        // a valid public custom domain still yields a routable URL.
        $result = WebhookUrl::build(
            'https://my-store.com',
            'yes',
            fn() => '',
            'https://my-store.com',
            self::GATEWAY
        );

        $this->assertSame(
            'https://my-store.com?wc-api=' . self::GATEWAY . '&source_news=webhooks',
            $result
        );
    }

    public function testFallbackWithEmptyApiUrlReturnsEmpty(): void
    {
        // No custom domain + resolver returns '' (WC() unavailable) → no broken URL.
        $result = WebhookUrl::build(
            '',
            'no',
            fn() => '',
            'https://production-store.com',
            self::GATEWAY
        );

        $this->assertSame('', $result);
    }
}
