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

use Tobento\App\Translation\Web\SavedTranslationsInterface;

final class CollectedTranslations implements CollectedTranslationsInterface
{
    /**
     * Create a new CollectedTranslations instance.
     *
     * @param SavedTranslationsInterface $saved
     */
    public function __construct(
        protected SavedTranslationsInterface $saved
    ) {}

    /**
     * Returns all saved translations.
     *
     * @return SavedTranslationsInterface
     */
    public function saved(): SavedTranslationsInterface
    {
        return $this->saved;
    }
}