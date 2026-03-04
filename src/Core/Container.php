<?php

namespace CargoDocsStudio\Core;

class Container
{
    /** @var array<string,mixed> */
    private array $instances = [];

    public function set(string $id, mixed $service): void
    {
        $this->instances[$id] = $service;
    }

    public function get(string $id): mixed
    {
        return $this->instances[$id] ?? null;
    }
}
