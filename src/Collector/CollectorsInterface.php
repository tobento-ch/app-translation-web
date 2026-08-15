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

use Countable;
use IteratorAggregate;

/**
 * Represents a collection of collectors.
 *
 * @extends IteratorAggregate<string, CollectorInterface>
 */
interface CollectorsInterface extends IteratorAggregate, Countable
{
    /**
     * Returns true if collector by id exists, otherwise false.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool;
    
    /**
     * Returns a collector by id or null if not found.
     *
     * @param string $id
     * @return null|CollectorInterface
     */
    public function get(string $id): null|CollectorInterface;
    
    /**
     * Returns the first collector or null if none.
     *
     * @return null|CollectorInterface
     */
    public function first(): null|CollectorInterface;
    
    /**
     * Returns all collectors.
     *
     * @return array<string, CollectorInterface>
     */
    public function all(): array;
    
    /**
     * Returns all collector ids.
     *
     * @return array<int, string>
     */
    public function ids(): array;

    /**
     * Returns a new instance with the filtered collectors.
     *
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): static;

    /**
     * Returns a new instance with only the translations ids provided.
     *
     * @param string ...$id
     * @return static
     */
    public function only(string ...$id): static;

    /**
     * Returns a new instance except the translations ids provided.
     *
     * @param string ...$id
     * @return static
     */
    public function except(string ...$id): static;
}