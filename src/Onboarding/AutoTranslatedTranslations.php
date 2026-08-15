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

final class AutoTranslatedTranslations implements AutoTranslatedTranslationsInterface
{
    /**
     * Create a new AutoTranslatedTranslations instance.
     *
     * @param string $locale The locale for which translations were auto-translated.
     * @param string $appId The application identifier.
     * @param SavedTranslationsInterface $saved The translations that were auto-translated.
     */
    public function __construct(
        protected string $locale,
        protected string $appId,
        protected SavedTranslationsInterface $saved,
    ) {}

    /**
     * Returns the locale for which translations were auto-translated.
     *
     * @return string
     */
    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * Returns the application identifier the translations belong to.
     *
     * @return string
     */
    public function appId(): string
    {
        return $this->appId;
    }

    /**
     * Returns all processed translations, including auto-translated and skipped entries.
     *
     * @return SavedTranslationsInterface
     */
    public function saved(): SavedTranslationsInterface
    {
        return $this->saved;
    }
}