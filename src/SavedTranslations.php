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

namespace Tobento\App\Translation\Web;

use ArrayIterator;
use Traversable;

final class SavedTranslations implements SavedTranslationsInterface
{
    /**
     * @var array<int, SavedTranslationInterface>
     */
    protected array $items = [];

    /**
     * Add a saved translation to the collection.
     *
     * @param SavedTranslationInterface $saved
     * @return static
     */
    public function add(SavedTranslationInterface $saved): static
    {
        $this->items[] = $saved;
        return $this;
    }

    /**
     * Returns all saved translations.
     *
     * @return array<int, SavedTranslationInterface>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Returns a new instance with the filtered translations.
     *
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): static
    {
        $new = clone $this;
        $new->items = array_values(array_filter($new->items, $callback));
        return $new;
    }

    /**
     * Returns a new instance with only the created translations.
     *
     * @return static
     */
    public function created(): static
    {
        return $this->filter(
            fn (SavedTranslationInterface $t): bool => $t->status() === SavedTranslationStatus::CREATED
        );
    }

    /**
     * Returns a new instance with only the updated translations.
     *
     * @return static
     */
    public function updated(): static
    {
        return $this->filter(
            fn (SavedTranslationInterface $t): bool => $t->status() === SavedTranslationStatus::UPDATED
        );
    }

    /**
     * Returns a new instance with only the skipped translations.
     *
     * @return static
     */
    public function skipped(): static
    {
        return $this->filter(
            fn (SavedTranslationInterface $t): bool => $t->status() === SavedTranslationStatus::SKIPPED
        );
    }
    
    /**
     * Retrieve an external iterator.
     *
     * @return Traversable<int, SavedTranslationInterface>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Count elements of the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }
}