<?php

namespace MercadoPago\Woocommerce\Tests\Funnel;

/** Options-table boundary double. Real MySQL concurrency is validated separately. */
class OptionsDatabase
{
    public string $options = 'wp_options';
    public array $tables = [];
    public string $last_error = '';
    public bool $suppressed = false;
    public bool $failReads = false;
    public bool $failWrites = false;
    public bool $failRelease = false;
    public bool $failMirrors = false;
    public ?\Closure $afterInsert = null;
    /** Database server clock; it can drift from the PHP clock that writes lease expiries. */
    public ?int $databaseNow = null;

    public function suppress_errors($suppress = true)
    {
        $previous = $this->suppressed;
        $this->suppressed = $suppress;
        return $previous;
    }

    public function prepare($query, ...$args)
    {
        return json_encode([$query, $args]);
    }

    public function insert($table, $data, $formats)
    {
        if ($this->failWrites || isset($this->tables[$table][$data['option_name']])) {
            return false;
        }
        $this->tables[$table][$data['option_name']] = $data;
        if ($this->afterInsert) {
            ($this->afterInsert)();
        }
        return 1;
    }

    public function get_var($prepared)
    {
        [$query, $args] = json_decode($prepared, true);
        if ($this->failReads) {
            return null;
        }
        $value = $this->tables[$this->options][$args[0]]['option_value'] ?? null;
        if (count($args) > 1 && ($value !== $args[1] || (int) $value <= $this->expiryClock($query, $args[2] ?? null))) {
            return null;
        }
        return $value;
    }

    public function get_results($prepared)
    {
        [, $args] = json_decode($prepared, true);
        $this->last_error = $this->failReads ? 'synthetic-database-error' : '';
        if ($this->failReads) {
            return null;
        }
        return array_values(array_map(function ($row) {
            return (object) $row;
        }, array_intersect_key($this->tables[$this->options] ?? [], array_flip($args))));
    }

    public function update($table, $data, $where, $formats, $whereFormats)
    {
        if ($this->failWrites) {
            return false;
        }
        if (($this->tables[$table][$where['option_name']]['option_value'] ?? null) !== $where['option_value']) {
            return 0;
        }
        $this->tables[$table][$where['option_name']]['option_value'] = $data['option_value'];
        return 1;
    }

    public function delete($table, $where, $formats)
    {
        if ($this->failRelease) {
            return false;
        }
        if (($this->tables[$table][$where['option_name']]['option_value'] ?? null) !== $where['option_value']) {
            return 0;
        }
        unset($this->tables[$table][$where['option_name']]);
        return 1;
    }

    public function query($prepared)
    {
        [$query, $args] = json_decode($prepared, true);
        [$name, $value, $lockName, $owner] = $args;
        if ($this->failWrites || isset($this->tables[$this->options][$name])) {
            return false;
        }
        $current = $this->tables[$this->options][$lockName]['option_value'] ?? '';
        if ($current !== $owner || (int) $current <= $this->expiryClock($query, $args[4] ?? null)) {
            return 0;
        }
        $this->put($name, $value);
        return 1;
    }

    /** Evaluates the expiry comparison like MySQL: UNIX_TIMESTAMP() reads the database clock. */
    private function expiryClock(string $query, ?int $bound): int
    {
        if (strpos($query, 'UNIX_TIMESTAMP()') !== false) {
            return $this->databaseNow ?? time();
        }
        if ($bound === null) {
            throw new \LogicException('Expiry comparison without a clock.');
        }
        return $bound;
    }

    public function put(string $name, string $value): void
    {
        $this->tables[$this->options][$name] = ['option_name' => $name, 'option_value' => $value, 'autoload' => 'no'];
    }
}
