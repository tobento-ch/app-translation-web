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

/**
 * Represents the result of saving a translation.
 */
interface SavedTranslationInterface
{
    /**
     * Returns the save status (created, updated, skipped).
     *
     * @return SavedTranslationStatus
     */
    public function status(): SavedTranslationStatus;

    /**
     * Returns the translation entity that was processed.
     *
     * @return TranslationEntityInterface
     */
    public function entity(): TranslationEntityInterface;
    
    /**
     * Returns the skipped reason or null if none.
     *
     * @return null|string
     */
    public function skippedReason(): null|string;
}