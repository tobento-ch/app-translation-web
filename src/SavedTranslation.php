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

class SavedTranslation implements SavedTranslationInterface
{
    /**
     * Create a new instance.
     *
     * @param SavedTranslationStatus $status
     * @param TranslationEntityInterface $entity
     * @param null|string $skippedReason
     */
    public function __construct(
        protected SavedTranslationStatus $status,
        protected TranslationEntityInterface $entity,
        protected null|string $skippedReason = null,
    ) {}
    
    /**
     * Returns the save status (created, updated, skipped).
     *
     * @return SavedTranslationStatus
     */
    public function status(): SavedTranslationStatus
    {
        return $this->status;
    }

    /**
     * Returns the translation entity that was processed.
     *
     * @return TranslationEntityInterface
     */
    public function entity(): TranslationEntityInterface
    {
        return $this->entity;
    }
    
    /**
     * Returns the skipped reason or null if none.
     *
     * @return null|string
     */
    public function skippedReason(): null|string
    {
        return $this->skippedReason;
    }
}