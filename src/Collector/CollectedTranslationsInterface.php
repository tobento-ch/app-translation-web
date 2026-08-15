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
use Tobento\App\Translation\Web\SavedTranslationsInterface;

/**
 * Represents the result of collecting translations.
 */
interface CollectedTranslationsInterface
{
    /**
     * Returns all saved translations.
     *
     * @return SavedTranslationsInterface
     */
    public function saved(): SavedTranslationsInterface;
}