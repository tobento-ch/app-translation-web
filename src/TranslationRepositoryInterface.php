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

use Tobento\Service\Repository\RepositoryInterface;
use Tobento\Service\Storage\ItemsInterface;

interface TranslationRepositoryInterface extends RepositoryInterface
{
    /**
     * Returns the message locale.
     *
     * @return string
     */
    public function messageLocale(): string;
    
    /**
     * Returns a new instance with the given locale.
     *
     * @param string $locale
     * @return static
     */
    public function withMessageLocale(string $locale): static;
    
    /**
     * Creates or updates a translation entry using the provided parameters.
     *
     * @param string $appId
     * @param string $resourceName
     * @param string $resourceLocale
     * @param string $resourceGroup
     * @param null|string $resourceFilename
     * @param int $resourcePriority
     * @param string $message
     * @param string $translation
     * @param string $translatedBy
     * @return SavedTranslationInterface
     */
    public function saveTranslation(
        string $appId,
        string $resourceName,
        string $resourceLocale,
        string $resourceGroup,
        null|string $resourceFilename,
        int $resourcePriority,
        string $message,
        string $translation,
        string $translatedBy,
    ): SavedTranslationInterface;
    
    /**
     * Publishes translations for the given parameters.
     *
     * @param string $appId
     * @param null|string $resourceName
     * @param null|string $resourceLocale
     * @return SavedTranslationsInterface
     */
    public function publishTranslations(
        string $appId,
        null|string $resourceName = null,
        null|string $resourceLocale = null,
    ): SavedTranslationsInterface;
    
    /**
     * Returns all translations, optionally filtered by appId and locale.
     *
     * @param string|null $appId App ID to filter by, or null for all app IDs.
     * @param string|null $locale Locale to filter by, or null for all locales.
     * @return ItemsInterface Found translation records.
     */
    public function findAllBy(null|string $appId = null, null|string $locale = null): ItemsInterface;    
    
    /**
     * Returns all published translations, optionally filtered by appId and locale.
     *
     * @param string|null $appId App ID to filter by, or null for all app IDs.
     * @param string|null $locale Locale to filter by, or null for all locales.
     * @return ItemsInterface Published translation records.
     */
    public function findAllPublished(null|string $appId = null, null|string $locale = null): ItemsInterface;
    
    /**
     * Returns all modified translations, optionally filtered by appId, status and locale.
     *
     * @param string|null $appId App ID to filter by, or null for all app IDs.
     * @param null|string $status Status to filter by, or null for all statuses.
     * @param string|null $locale Locale to filter by, or null for all locales.
     * @return ItemsInterface Published translation records.
     */
    public function findAllModified(
        null|string $appId = null,
        null|string $status = null,
        null|string $locale = null
    ): ItemsInterface;
    
    /**
     * Resets the modified state on the given translations and returns them.
     *
     * @param iterable<TranslationEntityInterface> $translations
     * @return ItemsInterface
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function resetModified(iterable $translations): ItemsInterface;
    
    /**
     * Returns distinct values for the given column as a key/value array.
     *
     * @param string $column The column name to extract unique values from.
     * @return array<string, string> The distinct values indexed by themselves.
     */
    public function distinctValues(string $column): array;
}