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

use Tobento\Service\MachineTranslator\MachineTranslatorInterface;

interface OnboardingInterface
{
    /**
     * Creates missing translation entries for the given locale and app.
     *
     * @param string $locale The locale to create entries for.
     * @param string $appId The application identifier.
     * @return CreatedTranslationsInterface
     */
    public function createTranslationEntries(
        string $locale,
        string $appId,
    ): CreatedTranslationsInterface;

    /**
     * Returns the machine translator used for auto-translation, if available.
     *
     * @return null|MachineTranslatorInterface
     */
    public function machineTranslator(): null|MachineTranslatorInterface;
    
    /**
     * Indicates whether auto-translation is supported.
     *
     * @return bool
     */
    public function supportsAutoTranslate(): bool;

    /**
     * Automatically translates translation entries for the given locale and app.
     *
     * @param string $locale The locale to translate entries for.
     * @param string $appId The application identifier.
     * @return AutoTranslatedTranslationsInterface
     */
    public function autoTranslateEntries(
        string $locale,
        string $appId
    ): AutoTranslatedTranslationsInterface;
    
    /**
     * Automatically translates translation entries for the given parameters.
     *
     * @param array $where
     * @param array $orderBy
     * @param array $limit
     * @return AutoTranslatedTranslationsInterface
     */
    public function autoTranslateEntriesBy(
        array $where,
        array $orderBy,
        array $limit
    ): AutoTranslatedTranslationsInterface;

    /**
     * Publishes translations for the given locale and app.
     *
     * "Publishing" means updating the translation entries' status to "published".
     * No files are generated and no external publishing mechanism is triggered.
     *
     * @param string $locale The locale whose translations should be published.
     * @param string $appId The application identifier.
     * @return PublishedTranslationsInterface
     */
    public function publishTranslations(
        string $locale,
        string $appId
    ): PublishedTranslationsInterface;
}