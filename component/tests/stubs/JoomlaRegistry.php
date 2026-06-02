<?php

namespace Joomla\Registry;

class Registry
{
    /** @var array<string,mixed> */
    private array $data = [];

    public function __construct(mixed $data = null)
    {
        if (is_array($data)) {
            $this->data = $data;
        }
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return $this->data[$path] ?? $default;
    }

    public function set(string $path, mixed $value): mixed
    {
        $this->data[$path] = $value;
        return $value;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function __toString(): string
    {
        return json_encode($this->data) ?: '{}';
    }
}
