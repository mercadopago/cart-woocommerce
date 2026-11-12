<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Form;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @covers \MercadoPago\Woocommerce\Helpers\Form
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FormTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private array $originalPost;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->originalPost = $_POST;
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;
        WP_Mock::tearDown();
    }

    private function mockGetInput($input): void
    {
        WP_Mock::userFunction('MercadoPago\\Woocommerce\\Helpers\\filter_input_array')
            ->once()
            ->with(INPUT_GET, FILTER_SANITIZE_SPECIAL_CHARS)
            ->andReturn($input);
    }

    /**
     * @dataProvider requestMethodStringInputs
     */
    public function testRequestMethodUnslashesAndSanitizesTheServerMethod(
        string $method,
        string $sanitized,
        string $expected
    ): void {
        $originalServer = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = $method;
            WP_Mock::userFunction('wp_unslash')->once()->with($method)->andReturn($method);
            WP_Mock::userFunction('sanitize_text_field')->once()->with($method)->andReturn($sanitized);

            $this->assertSame($expected, Form::requestMethod());
        } finally {
            $_SERVER = $originalServer;
        }
    }

    public static function requestMethodStringInputs(): array
    {
        return [
            'POST' => ['POST', 'POST', 'POST'],
            'GET' => ['GET', 'GET', 'GET'],
            'lowercase' => ['post', 'post', 'POST'],
            'markup is sanitized' => ['<b>post</b>', 'post', 'POST'],
            'empty' => ['', '', ''],
        ];
    }

    /**
     * @dataProvider requestMethodInvalidInputs
     */
    public function testRequestMethodReturnsEmptyWithoutReadingMissingOrInvalidValues($method): void
    {
        $originalServer = $_SERVER;
        try {
            if ($method === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $method;
            }
            WP_Mock::userFunction('wp_unslash')->never();
            WP_Mock::userFunction('sanitize_text_field')->never();

            $this->assertSame('', Form::requestMethod());
        } finally {
            $_SERVER = $originalServer;
        }
    }

    public static function requestMethodInvalidInputs(): array
    {
        return [
            'missing' => [null],
            'array is invalid' => [['POST']],
            'integer is invalid' => [1],
        ];
    }

    public function testPostFieldsSanitizesStringsWithoutAddingPostObjectFieldsOrMutatingInput(): void
    {
        $_POST = ['notice' => '<b>synthetic</b>', 'unknown_field' => 'preserved-key'];
        WP_Mock::userFunction('sanitize_post')->never();
        WP_Mock::userFunction('map_deep')->never();
        WP_Mock::userFunction('wp_unslash')->once()->with('<b>synthetic</b>')->andReturn('<b>unescaped</b>');
        WP_Mock::userFunction('sanitize_text_field')->once()->with('<b>unescaped</b>')->andReturn('unescaped');
        WP_Mock::userFunction('wp_unslash')->once()->with('preserved-key')->andReturn('preserved-key');
        WP_Mock::userFunction('sanitize_text_field')->once()->with('preserved-key')->andReturn('preserved-key');

        $this->assertSame(['notice' => 'unescaped', 'unknown_field' => 'preserved-key'], Form::sanitizedPostFields());
        $this->assertSame(['notice' => '<b>synthetic</b>', 'unknown_field' => 'preserved-key'], $_POST);
    }

    /**
     * @dataProvider invalidPostFieldShapes
     */
    public function testPostFieldsPreservesNonStringShapesForStrictSchemaRejection($value): void
    {
        $_POST = ['event' => $value];
        WP_Mock::userFunction('sanitize_post')->never();
        WP_Mock::userFunction('map_deep')->never();
        WP_Mock::userFunction('wp_unslash')->never();
        WP_Mock::userFunction('sanitize_text_field')->never();

        $this->assertSame(['event' => $value], Form::sanitizedPostFields());
    }

    public static function invalidPostFieldShapes(): array
    {
        return [
            'nested' => [['view']],
            'integer' => [1],
            'boolean' => [false],
            'null' => [null],
        ];
    }

    public function testEmptyPostFieldsRemainEmptyWithoutPostObjectDefaults(): void
    {
        WP_Mock::userFunction('sanitize_post')->never();
        WP_Mock::userFunction('wp_unslash')->never();
        WP_Mock::userFunction('sanitize_text_field')->never();

        $this->assertSame([], Form::sanitizedPostFields());
    }

    public function testPostSelectsKeyFromWordPressSanitizedPost(): void
    {
        $_POST = ['amount' => 'raw-value', 'other' => 'unrelated'];
        WP_Mock::userFunction('sanitize_post')->once()->with($_POST)->andReturn(['amount' => 'wp-post-value']);
        WP_Mock::userFunction('sanitize_text_field')->once()->with('wp-post-value')->andReturn('clean-value');
        WP_Mock::userFunction('map_deep')->never();

        $this->assertSame('clean-value', Form::sanitizedPostData('amount'));
    }

    /**
     * @dataProvider scalarInputs
     */
    public function testPostPassesScalarAndNullToTextSanitizer($value): void
    {
        $_POST = ['field' => $value];
        WP_Mock::userFunction('sanitize_post')->once()->with($_POST)->andReturn($_POST);
        WP_Mock::userFunction('sanitize_text_field')->once()->with(Mockery::on(function ($actual) use ($value) {
            return $actual === $value;
        }))->andReturn('sanitized-value');
        WP_Mock::userFunction('map_deep')->never();

        $this->assertSame('sanitized-value', Form::sanitizedPostData('field'));
    }

    public function scalarInputs(): array
    {
        return [
            'text' => ['<b>checkout</b>'],
            'integer zero' => [0],
            'float' => [12.34],
            'false' => [false],
            'null' => [null],
        ];
    }

    public function testGetSelectsRequestedKeyFromNativeInputResult(): void
    {
        $this->mockGetInput(['nonce' => 'get-nonce', 'other' => 'ignored']);
        WP_Mock::userFunction('sanitize_text_field')->once()->with('get-nonce')->andReturn('clean-nonce');
        WP_Mock::userFunction('map_deep')->never();

        $this->assertSame('clean-nonce', Form::sanitizedGetData('nonce'));
    }

    /**
     * @dataProvider absentGetInputs
     */
    public function testGetMissingInputOrKeyPassesNullToTextSanitizer($input, ?string $key): void
    {
        $this->mockGetInput($input);
        WP_Mock::userFunction('sanitize_text_field')->once()->with(null)->andReturn('');
        WP_Mock::userFunction('map_deep')->never();

        $this->assertSame('', Form::sanitizedGetData($key));
    }

    public function absentGetInputs(): array
    {
        return [
            'no native input' => [null, null],
            'missing key' => [['other' => 'value'], 'missing'],
            'null key value' => [['nonce' => null], 'nonce'],
        ];
    }

    public function testWholePostPassesComposedResultToMapDeepAndPropagatesResult(): void
    {
        $_POST = ['raw' => 'original'];
        $input = ['checkout' => ['field' => 'raw-leaf', 'nullable' => null]];
        $expected = ['checkout' => ['field' => 'clean-leaf', 'nullable' => '']];
        WP_Mock::userFunction('sanitize_post')->once()->with($_POST)->andReturn($input);
        $callback = $this->expectMapDeep($input, $expected);

        $this->assertSame($expected, Form::sanitizedPostData());
        $this->assertLeafSanitizer($callback());
    }

    public function testWholeGetPassesComposedNativeResultToMapDeep(): void
    {
        $input = ['checkout' => ['field' => 'raw-leaf', 'nullable' => null]];
        $expected = ['checkout' => ['field' => 'clean-leaf', 'nullable' => '']];
        $this->mockGetInput($input);
        $callback = $this->expectMapDeep($input, $expected);

        $this->assertSame($expected, Form::sanitizedGetData());
        $this->assertLeafSanitizer($callback());
    }

    public function testSelectedPostObjectIsDelegatedWithoutLosingObjectShape(): void
    {
        $input = (object) ['nested' => (object) ['field' => 'raw-leaf', 'nullable' => null]];
        $expected = (object) ['nested' => (object) ['field' => 'clean-leaf', 'nullable' => '']];
        $_POST = ['checkout' => $input];
        WP_Mock::userFunction('sanitize_post')->once()->with($_POST)->andReturn($_POST);
        $callback = $this->expectMapDeep($input, $expected);

        $this->assertSame($expected, Form::sanitizedPostData('checkout'));
        $this->assertLeafSanitizer($callback());
    }

    private function expectMapDeep($input, $expected): callable
    {
        $capturedCallback = null;
        WP_Mock::userFunction('map_deep')->once()->with($input, Mockery::on(function ($callback) use (&$capturedCallback) {
            $capturedCallback = $callback;
            return is_callable($callback);
        }))->andReturn($expected);

        return function () use (&$capturedCallback) {
            return $capturedCallback;
        };
    }

    private function assertLeafSanitizer(callable $callback): void
    {
        WP_Mock::userFunction('sanitize_text_field')->once()->with('raw-leaf')->andReturn('clean-leaf');
        WP_Mock::userFunction('sanitize_text_field')->once()->with(null)->andReturn('');

        $this->assertSame('clean-leaf', $callback('raw-leaf'));
        $this->assertSame('', $callback(null));
    }
}
