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

namespace Tobento\App\Translation\Web\Onboarding;

use Tobento\App\Translation\Web\SavedTranslationsInterface;

interface CreatedTranslationsInterface
{
    /**
     * Returns the locale for which translations were created.
     *
     * @return string
     */
    public function locale(): string;
    
    /**
     * Returns the application identifier the translations belong to.
     *
     * @return string
     */
    public function appId(): string;
    
    /**
     * Returns all translations that were created or saved.
     *
     * @return SavedTranslationsInterface
     */
    public function saved(): SavedTranslationsInterface;
}