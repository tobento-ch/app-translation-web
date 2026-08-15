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

use Countable;
use IteratorAggregate;

/**
 * Represents a collection of saved translations.
 *
 * @extends IteratorAggregate<int, SavedTranslationInterface>
 */
interface SavedTranslationsInterface extends IteratorAggregate, Countable
{
    /**
     * Add a saved translation to the collection.
     *
     * @param SavedTranslationInterface $saved
     * @return static
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function add(SavedTranslationInterface $saved): static;

    /**
     * Returns all saved translations.
     *
     * @return array<int, SavedTranslationInterface>
     */
    public function all(): array;

    /**
     * Returns a new instance with the filtered translations.
     *
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): static;
    
    /**
     * Returns a new instance with only the created translations.
     *
     * @return static
     */
    public function created(): static;
    
    /**
     * Returns a new instance with only the updated translations.
     *
     * @return static
     */
    public function updated(): static;

    /**
     * Returns a new instance with only the skipped translations.
     *
     * @return static
     */
    public function skipped(): static;
}