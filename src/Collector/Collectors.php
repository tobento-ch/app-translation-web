<?php

/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

declare(strict_types=1);

namespace Tobento\App\Translation\Web\Collector;

use ArrayIterator;
use Traversable;

final class Collectors implements CollectorsInterface
{
    /**
     * @var array<string, CollectorInterface>
     */
    protected array $collectors = [];
    
    /**
     * Create a new instance.
     *
     * @param CollectorInterface ...$collectors
     */
    public function __construct(
        CollectorInterface ...$collectors,
    ) {
        foreach($collectors as $collector) {
            $this->collectors[$collector->id()] = $collector;
        }
    }

    /**
     * Returns true if collector by id exists, otherwise false.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->collectors);
    }
    
    /**
     * Returns a collector by id or null if not found.
     *
     * @param string $id
     * @return null|CollectorInterface
     */
    public function get(string $id): null|CollectorInterface
    {
        return $this->collectors[$id] ?? null;
    }
    
    /**
     * Returns the first collector or null if none.
     *
     * @return null|CollectorInterface
     */
    public function first(): null|CollectorInterface
    {
        $key = array_key_first($this->collectors);

        return $key === null ? null : $this->collectors[$key];
    }
    
    /**
     * Returns all collectors.
     *
     * @return array<string, CollectorInterface>
     */
    public function all(): array
    {
        return $this->collectors;
    }
    
    /**
     * Returns all collector ids.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->collectors);
    }

    /**
     * Returns a new instance with the filtered collectors.
     *
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): static
    {
        $new = clone $this;
        $new->collectors = array_filter($new->collectors, $callback, ARRAY_FILTER_USE_BOTH);
        return $new;
    }

    /**
     * Returns a new instance with only the translations ids provided.
     *
     * @param string ...$id
     * @return static
     */
    public function only(string ...$id): static
    {
        return $this->filter(
            fn (CollectorInterface $c): bool => in_array($c->id(), $id, true)
        );
    }

    /**
     * Returns a new instance except the translations ids provided.
     *
     * @param string ...$id
     * @return static
     */
    public function except(string ...$id): static
    {
        return $this->filter(
            fn (CollectorInterface $c): bool => !in_array($c->id(), $id, true)
        );
    }
    
    /**
     * Retrieve an external iterator.
     *
     * @return Traversable<string, CollectorInterface>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->collectors);
    }

    /**
     * Count elements of the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->collectors);
    }
}