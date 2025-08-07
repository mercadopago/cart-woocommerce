<?php

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;

if (!class_exists('WP_Theme')) {
    class WP_Theme
    {
        private $headers;
        private $headers_sanitized;

        public function __construct()
        {
            $this->headers = array('headers' => array('Name' => 'Test Theme', 'Version' => '1.0.0'));
        }

        public function cache_get($headers)
        {
            return $this->headers[$headers];
        }

        public function get($header)
        {
            if (!isset($this->headers_sanitized)) {
                $this->headers_sanitized = $this->cache_get('headers');
                if (!is_array($this->headers_sanitized)) {
                    $this->headers_sanitized = array();
                }
            }

            if (isset($this->headers_sanitized[$header])) {
                return $this->headers_sanitized[$header];
            }

            return $this->headers_sanitized[$header];
        }
    }
}

if (!class_exists('WC_Payment_Gateway')) {
    class WC_Payment_Gateway
    {
    }
}

class StoreTest extends TestCase
{
    private Store $store;
    private Options $options;

    protected function setUp(): void
    {
        $this->options = $this->createMock(Options::class);
        $this->store = new Store($this->options);
    }

    public function testSetCodeChallenge(): void
    {
        $codeChallenge = 'test_code_challenge';

        $this->options->expects($this->once())
            ->method('set')
            ->with($this->equalTo('_mp_integration_code_challenge'), $this->equalTo($codeChallenge));

        $this->store->setCodeChallenge($codeChallenge);
    }

    public function testSetCodeChallengeAndVerifier(): void
    {
        $this->options->expects($this->exactly(2))
            ->method('set')
            ->withConsecutive(
                [$this->equalTo('_mp_integration_code_verifier'), $this->isType('string')],
                [$this->equalTo('_mp_integration_code_challenge'), $this->isType('string')]
            );

        [$codeChallenge, $codeVerifier] = $this->store->setCodeChallengeAndVerifier();

        $this->assertIsString($codeChallenge);
        $this->assertIsString($codeVerifier);
    }

    public function testGetCodeChallenge(): void
    {
        $expectedCodeChallenge = 'test_code_challenge';

        $this->options->expects($this->once())
            ->method('get')
            ->with($this->equalTo('_mp_integration_code_challenge'), $this->equalTo(''))
            ->willReturn($expectedCodeChallenge);

        $this->assertEquals($expectedCodeChallenge, $this->store->getCodeChallenge());
    }

    public function testGetCodeVerifier(): void
    {
        $expectedCodeVerifier = 'test_code_verifier';

        $this->options->expects($this->once())
            ->method('get')
            ->with($this->equalTo('_mp_integration_code_verifier'), $this->equalTo(''))
            ->willReturn($expectedCodeVerifier);

        $this->assertEquals($expectedCodeVerifier, $this->store->getCodeVerifier());
    }

    public function testWpGetThemeNameAndVersion(): void
    {
        $stylesheet = 'test-theme';
        $themeRoot = '/path/to/themes';
        $expectedThemeName = 'Test Theme';
        $expectedThemeVersion = '1.0.0';

        global $wp_theme_directories;
        $wp_theme_directories = [$themeRoot];

        WP_Mock::userFunction('get_stylesheet', [
            'return' => $stylesheet,
        ]);

        WP_Mock::userFunction('get_raw_theme_root', [
            'args' => [$stylesheet],
            'return' => $themeRoot,
        ]);

        $store = new Store($this->options);

        $result = $store->wpGetThemeNameAndVersion($stylesheet, $themeRoot);

        $this->assertEquals(['theme_name' => $expectedThemeName, 'theme_version' => $expectedThemeVersion], $result);
    }

    public function testGetGatewayTitle(): void
    {
        $gatewayMock = $this->createMock(AbstractGateway::class);
        $defaultTitle = 'Default Gateway Title';
        $expectedTitle = 'Custom Gateway Title';

        $this->options->expects($this->once())
            ->method('getGatewayOption')
            ->with($this->equalTo($gatewayMock), $this->equalTo('title'), $this->equalTo($defaultTitle))
            ->willReturn($expectedTitle);

        $result = $this->store->getGatewayTitle($gatewayMock, $defaultTitle);

        $this->assertEquals($expectedTitle, $result);
    }
}
