<?php

namespace MercadoPago\Woocommerce\Tests\Mocks;

use Closure;

/**
 * Mock implementation of ArrayAccess for testing.
 * 
 * Provides a lazy-loading array mock that generates values on-demand
 * using a closure generator.
 * 
 */
class ArrayMock implements \ArrayAccess
{
    /**
     * Closure that generates mock values when executed.
     */
    private Closure $generator;

    /**
     * Values storage.
     */
    private array $array = [];

    /**
     * Array of keys that are allowed to be generated.
     * 
     * When set, only these keys will return generated values.
     */
    private array $only;

    /**
     * Array of keys that are excluded from generation.
     * 
     * When set, these keys will only return explicitly set values.
     */
    private array $except;

    /**
     * @param Closure $generator Closure that generates mock values when executed.
     */
    public function __construct(Closure $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Restrict generation to only specified keys.
     * 
     * When this method is called, only the specified keys will return generated
     * values. All other keys will return their explicitly set values.
     * 
     * @param mixed ...$only Keys that are allowed to generate values.
     * @throws \Exception When $except is already set.
     */
    public function only(...$only)
    {
        if (isset($this->except)) {
            throw new \Exception('$except already set');
        }

        $this->only = $only;
    }

    /**
     * Exclude specified keys from generation.
     * 
     * When this method is called, the specified keys will only return their
     * explicitly set values.
     * 
     * @param mixed ...$except Keys that are excluded from generation.
     * @throws \Exception When $only is already set.
     */
    public function except(...$except)
    {
        if (isset($this->only)) {
            throw new \Exception('$only already set');
        }

        $this->except = $except;
    }

    /**
     * Get a key value.
     * 
     * If the key doesn't exist in the internal array and is allowed to be generated,
     * a new value will be generated using the generator closure.
     */
    public function offsetGet($key)
    {
        if (isset($this->only) && !in_array($key, $this->only, true)) {
            return $this->array[$key];
        }

        if (isset($this->except) && in_array($key, $this->except, true)) {
            return $this->array[$key];
        }

        return $this->array[$key] ??= ($this->generator)();
    }

    /**
     * Check if an key exists.
     * 
     * @return bool True if the key is set or is allowed to be generated.
     */
    public function offsetExists($key): bool
    {
        if (isset($this->only)) {
            return in_array($key, $this->only, true) || isset($this->array[$key]);
        }

        if (isset($this->except) && in_array($key, $this->except, true)) {
            return isset($this->array[$key]);
        }

        return true;
    }

    /**
     * Set a key value.
     */
    public function offsetSet($key, $value): void
    {
        $this->array[$key] = $value;
    }

    /**
     * Unset a key value.
     */
    public function offsetUnset($key): void
    {
        unset($this->array[$key]);
    }

    /**
     * Returns an ArrayMock instance that generates random words for translation keys.
     */
    public static function mockTranslations(): self
    {
        return new ArrayMock(fn() => random()->word());
    }
}
