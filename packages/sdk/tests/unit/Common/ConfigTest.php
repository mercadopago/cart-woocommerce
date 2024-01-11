<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Common;

use MercadoPago\PP\Sdk\Common\Config;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Common\Config
 */
class ConfigTest extends TestCase
{
    /**
     * @var Config
     */
    private $config;

	/**
	 * @inheritdoc
	 */
	protected function setUp(): void
	{
        $this->config = new Config();
    }

    function testGetAndSetSuccess()
    {
        $this->config->__set('access_token', 'XXX');

        $actual = $this->config->__get('access_token');
        $expected = 'XXX';

        $this->assertEquals($expected, $actual);
    }
}
