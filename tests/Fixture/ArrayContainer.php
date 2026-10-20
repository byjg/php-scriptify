<?php

namespace Tests\Fixture;

use Psr\Container\ContainerInterface;

/**
 * A container holding the given entries, and counting what it built.
 */
class ArrayContainer implements ContainerInterface
{
    public int $built = 0;

    /** @param array<string, \Closure(): mixed> $entries */
    public function __construct(private array $entries)
    {
    }

    public function get(string $id): mixed
    {
        $this->built++;
        return ($this->entries[$id])();
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}
