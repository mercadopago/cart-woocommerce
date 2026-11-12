<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

/** In-memory model of the options table's unique name and conditional writes. */
class SmartPixStatusLockDatabase
{
    public string $options = 'wp_options';

    public array $rows = [];

    public bool $failing = false;

    public bool $suppressErrors = false;

    /** @var string[] Lock names whose insert ran while wpdb could still print its error. */
    public array $insertsWithVisibleErrors = [];

    public function suppress_errors($suppress = true): bool
    {
        $previous = $this->suppressErrors;
        $this->suppressErrors = (bool) $suppress;
        return $previous;
    }

    public function insert($table, array $data, array $format)
    {
        if (!$this->suppressErrors) {
            $this->insertsWithVisibleErrors[] = $data['option_name'];
        }
        if ($this->failing || isset($this->rows[$data['option_name']])) {
            return false;
        }
        $this->rows[$data['option_name']] = $data;
        return 1;
    }

    public function prepare($query, $name): string
    {
        return $name;
    }

    public function get_var($name)
    {
        return $this->failing ? null : ($this->rows[$name]['option_value'] ?? null);
    }

    public function update($table, array $data, array $where, array $format, array $whereFormat)
    {
        $name = $where['option_name'];
        if (!isset($this->rows[$name]) || $this->rows[$name]['option_value'] !== $where['option_value']) {
            return 0;
        }
        $this->rows[$name]['option_value'] = $data['option_value'];
        return 1;
    }

    public function delete($table, array $where, array $whereFormat)
    {
        $name = $where['option_name'];
        if (!isset($this->rows[$name]) || $this->rows[$name]['option_value'] !== $where['option_value']) {
            return 0;
        }
        unset($this->rows[$name]);
        return 1;
    }
}
