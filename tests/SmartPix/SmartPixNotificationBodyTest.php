<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use InvalidArgumentException;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationBody;
use PHPUnit\Framework\TestCase;

class SmartPixNotificationBodyTest extends TestCase
{
    private const WRAPPER = 'smartpixbodytest';

    /** Default PHP stream chunk size used when reading from user stream wrappers. */
    private const STREAM_CHUNK = 8192;

    /** @var string */
    private $wrapperClass = '';

    /** @var int */
    private $opened = 0;

    protected function tearDown(): void
    {
        if (in_array(self::WRAPPER, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::WRAPPER);
        }
        parent::tearDown();
    }

    public function testReadRawReturnsExactBytesAndDecodeReturnsTheJsonObject(): void
    {
        $body = '{"id":"ORDER_SYNTHETIC_001","type":"order","amount":12345678901234567890}';
        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, $body);
        rewind($stream);
        $opened = 0;
        $reader = new SmartPixNotificationBody(
            SmartPixNotificationBody::DEFAULT_MAX_BYTES,
            static function () use ($stream, &$opened) {
                $opened++;
                return $stream;
            }
        );

        $raw = $reader->readRaw((string) strlen($body));

        $this->assertSame(['accepted' => true, 'category' => null, 'raw_body' => $body], $raw);
        $this->assertSame([
            'accepted' => true,
            'category' => null,
            'payload' => ['id' => 'ORDER_SYNTHETIC_001', 'type' => 'order', 'amount' => '12345678901234567890'],
        ], $reader->decode($raw['raw_body']));
        $this->assertSame(1, $opened);
        $this->assertFalse(is_resource($stream));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public function rejectedContentLengthProvider(): array
    {
        return [
            'non numeric' => ['abc', SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'empty' => ['', SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'negative' => ['-1', SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'explicit plus sign' => ['+10', SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'leading zero' => ['012', SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'leading space' => [' 12', SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'trailing space' => ['12 ', SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'trailing newline' => ["12\n", SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'decimal' => ['1.5', SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'exponent' => ['1e3', SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD],
            'limit plus one' => ['16385', SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE],
            'beyond int range' => ['99999999999999999999999', SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE],
        ];
    }

    /** @dataProvider rejectedContentLengthProvider */
    public function testReadRawRejectsContentLengthWithoutOpeningTheStream(string $contentLength, string $category): void
    {
        $reader = new SmartPixNotificationBody(
            SmartPixNotificationBody::DEFAULT_MAX_BYTES,
            $this->memoryOpener('{"id":"ORDER_SYNTHETIC_001"}')
        );

        $this->assertSame($this->rawRejection($category), $reader->readRaw($contentLength));
        $this->assertSame(0, $this->opened);
    }

    public function testReadRawAcceptsABodyExactlyAtTheDefaultLimit(): void
    {
        $body = $this->jsonOfLength(SmartPixNotificationBody::DEFAULT_MAX_BYTES);
        $reader = new SmartPixNotificationBody(SmartPixNotificationBody::DEFAULT_MAX_BYTES, $this->memoryOpener($body));

        $this->assertSame(['accepted' => true, 'category' => null, 'raw_body' => $body], $reader->readRaw('16384'));
        $this->assertSame(1, $this->opened);
    }

    public function testReadRawAcceptsAnEmptyBodyButDecodeRejectsIt(): void
    {
        $reader = new SmartPixNotificationBody(SmartPixNotificationBody::DEFAULT_MAX_BYTES, $this->memoryOpener(''));

        $this->assertSame(['accepted' => true, 'category' => null, 'raw_body' => ''], $reader->readRaw('0'));
        $this->assertSame(
            $this->decodeRejection(SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD),
            $reader->decode('')
        );
    }

    public function testReadRawAcceptsAValidBodyWithoutContentLength(): void
    {
        $body = '{"id":"ORDER_SYNTHETIC_001"}';
        $reader = new SmartPixNotificationBody(SmartPixNotificationBody::DEFAULT_MAX_BYTES, $this->memoryOpener($body));

        $this->assertSame(['accepted' => true, 'category' => null, 'raw_body' => $body], $reader->readRaw(null));
    }

    /** A declared length that differs from the actual size is accepted while both stay within the limit. */
    public function testReadRawAcceptsAContentLengthThatDiffersFromTheActualSizeWithinTheLimit(): void
    {
        $body = '{"a":12}';
        $reader = new SmartPixNotificationBody(SmartPixNotificationBody::DEFAULT_MAX_BYTES, $this->memoryOpener($body));

        $this->assertSame(8, strlen($body));
        $this->assertSame(['accepted' => true, 'category' => null, 'raw_body' => $body], $reader->readRaw('10'));
    }

    public function testReadRawRejectsAnOversizedActualBodyAndConsumesAtMostLimitPlusOneBytes(): void
    {
        $stream = null;
        $reader = new SmartPixNotificationBody(
            SmartPixNotificationBody::DEFAULT_MAX_BYTES,
            $this->wrapperOpener(str_repeat('a', 100000), false, $stream)
        );

        $this->assertSame(
            $this->rawRejection(SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE),
            $reader->readRaw('10')
        );
        // PHP pulls user-wrapper data in fixed chunks, so the observable bound is limit + 1 rounded up to a chunk.
        $budget = SmartPixNotificationBody::DEFAULT_MAX_BYTES + 1;
        $bound = intdiv($budget + self::STREAM_CHUNK - 1, self::STREAM_CHUNK) * self::STREAM_CHUNK;
        $wrapperClass = $this->wrapperClass;
        $this->assertGreaterThanOrEqual($budget, $wrapperClass::$consumed);
        $this->assertLessThanOrEqual($bound, $wrapperClass::$consumed);
        $this->assertFalse(is_resource($stream));
    }

    /** @return array<string, array{0: mixed}> */
    public function nonResourceOpenerResultProvider(): array
    {
        return [
            'false' => [false],
            'null' => [null],
            'string' => ['php://input'],
        ];
    }

    /**
     * @dataProvider nonResourceOpenerResultProvider
     * @param mixed $openerResult
     */
    public function testReadRawReportsUnreadableBodyWhenTheOpenerFails($openerResult): void
    {
        $reader = new SmartPixNotificationBody(
            SmartPixNotificationBody::DEFAULT_MAX_BYTES,
            static function () use ($openerResult) {
                return $openerResult;
            }
        );

        $this->assertSame(
            $this->rawRejection(SmartPixNotificationBody::CATEGORY_UNREADABLE_BODY),
            $reader->readRaw('10')
        );
    }

    public function testReadRawReportsUnreadableBodyAndClosesTheStreamWhenReadingFails(): void
    {
        $stream = null;
        $reader = new SmartPixNotificationBody(
            SmartPixNotificationBody::DEFAULT_MAX_BYTES,
            $this->wrapperOpener('', true, $stream)
        );

        // Suppresses the engine warning a failing user stream may raise; only the result matters.
        $result = @$reader->readRaw('10');

        $this->assertSame($this->rawRejection(SmartPixNotificationBody::CATEGORY_UNREADABLE_BODY), $result);
        $this->assertFalse(is_resource($stream));
    }

    public function testReadRawRejectsAnActualBodyOverACustomLimitWithoutContentLength(): void
    {
        $reader = new SmartPixNotificationBody(8, $this->memoryOpener('{"a":123}'));

        $this->assertSame(
            $this->rawRejection(SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE),
            $reader->readRaw(null)
        );
    }

    public function testReadRawAcceptsAnActualBodyExactlyAtACustomLimit(): void
    {
        $reader = new SmartPixNotificationBody(8, $this->memoryOpener('{"a":12}'));

        $this->assertSame(['accepted' => true, 'category' => null, 'raw_body' => '{"a":12}'], $reader->readRaw(null));
    }

    /** @return array<string, array{0: string|null}> */
    public function rejectionAfterOpenProvider(): array
    {
        return [
            'oversized without content length' => [null],
            'oversized despite a small content length' => ['5'],
        ];
    }

    /** @dataProvider rejectionAfterOpenProvider */
    public function testReadRawClosesTheStreamOnRejectionAfterOpening(?string $contentLength): void
    {
        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, '{"a":123}');
        rewind($stream);
        $reader = new SmartPixNotificationBody(
            8,
            static function () use ($stream) {
                return $stream;
            }
        );

        $this->assertSame(
            $this->rawRejection(SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE),
            $reader->readRaw($contentLength)
        );
        $this->assertFalse(is_resource($stream));
    }

    public function testDecodeRejectsAnOversizedBodyBeforeParsing(): void
    {
        $reader = new SmartPixNotificationBody(8, $this->failingOpener());

        $this->assertSame(
            $this->decodeRejection(SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE),
            $reader->decode('{"a":123}')
        );
        // Invalid JSON over the limit proves the size check runs before parsing.
        $this->assertSame(
            $this->decodeRejection(SmartPixNotificationBody::CATEGORY_BODY_TOO_LARGE),
            $reader->decode(str_repeat('x', 9))
        );
    }

    /** @return array<string, array{0: string}> */
    public function invalidPayloadProvider(): array
    {
        return [
            'json list' => ['[1,2]'],
            'json string' => ['"x"'],
            'json number' => ['123'],
            'json null' => ['null'],
            'truncated object' => ['{"a":'],
            'nesting deeper than the limit' => [str_repeat('{"a":', 40) . '1' . str_repeat('}', 40)],
            'invalid utf-8' => ["{\"a\":\"\xff\"}"],
            'leading byte order mark' => ["\xEF\xBB\xBF{\"a\":1}"],
        ];
    }

    /** @dataProvider invalidPayloadProvider */
    public function testDecodeRejectsAnInvalidPayload(string $rawBody): void
    {
        $reader = new SmartPixNotificationBody(SmartPixNotificationBody::DEFAULT_MAX_BYTES, $this->failingOpener());

        $this->assertSame(
            $this->decodeRejection(SmartPixNotificationBody::CATEGORY_INVALID_PAYLOAD),
            $reader->decode($rawBody)
        );
    }

    public function testDecodeAcceptsLeadingWhitespaceBeforeTheObject(): void
    {
        $reader = new SmartPixNotificationBody(SmartPixNotificationBody::DEFAULT_MAX_BYTES, $this->failingOpener());

        $this->assertSame(
            ['accepted' => true, 'category' => null, 'payload' => ['a' => 1]],
            $reader->decode(" \t\r\n{\"a\":1}")
        );
    }

    /** @return array<string, array{0: int}> */
    public function invalidMaxBytesProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    /** @dataProvider invalidMaxBytesProvider */
    public function testConstructorRejectsANonPositiveLimit(int $maxBytes): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SmartPixNotificationBody($maxBytes);
    }

    /** @return callable(): mixed */
    private function memoryOpener(string $body): callable
    {
        return function () use ($body) {
            $this->opened++;
            $stream = fopen('php://memory', 'w+b');
            fwrite($stream, $body);
            rewind($stream);
            return $stream;
        };
    }

    /** @return callable(): mixed */
    private function failingOpener(): callable
    {
        return function () {
            $this->fail('The stream must not be opened.');
        };
    }

    private function jsonOfLength(int $length): string
    {
        $prefix = '{"a":"';
        $suffix = '"}';
        return $prefix . str_repeat('x', $length - strlen($prefix) - strlen($suffix)) . $suffix;
    }

    /** @return array{accepted: false, category: string, raw_body: null} */
    private function rawRejection(string $category): array
    {
        return ['accepted' => false, 'category' => $category, 'raw_body' => null];
    }

    /** @return array{accepted: false, category: string, payload: null} */
    private function decodeRejection(string $category): array
    {
        return ['accepted' => false, 'category' => $category, 'payload' => null];
    }

    /**
     * Opens a stream over a minimal read-only wrapper that counts delivered bytes
     * or fails every read; the opened resource is exposed through $stream.
     *
     * @param resource|null $stream
     * @return callable(): mixed
     */
    private function wrapperOpener(string $data, bool $failReads, &$stream): callable
    {
        $wrapper = new class {
            /** @var string */
            public static $data = '';

            /** @var bool */
            public static $failReads = false;

            /** @var int */
            public static $consumed = 0;

            /** @var resource|null */
            public $context;

            /** @var int */
            private $position = 0;

            public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
            {
                return true;
            }

            /** @return string|false */
            public function stream_read(int $count)
            {
                if (self::$failReads) {
                    return false;
                }
                $chunk = substr(self::$data, $this->position, $count);
                $this->position += strlen($chunk);
                self::$consumed += strlen($chunk);
                return $chunk;
            }

            public function stream_eof(): bool
            {
                return !self::$failReads && $this->position >= strlen(self::$data);
            }

            public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
            {
                return false;
            }
        };
        $class = get_class($wrapper);
        $class::$data = $data;
        $class::$failReads = $failReads;
        $class::$consumed = 0;
        $this->wrapperClass = $class;
        stream_wrapper_register(self::WRAPPER, $class);

        return static function () use (&$stream) {
            $stream = fopen(self::WRAPPER . '://body', 'rb');
            return $stream;
        };
    }
}
