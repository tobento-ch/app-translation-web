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

use DateTimeInterface;
use Tobento\Service\Support\Arrayable;

/**
 * TranslationEntityInterface
 *
 * Represents a single translation record stored in the repository.
 */
interface TranslationEntityInterface extends Arrayable
{
    /**
     * Returns the translation ID.
     *
     * @return int
     */
    public function id(): int;

    /**
     * Returns the application identifier.
     *
     * @return string
     */
    public function appId(): string;

    /**
     * Returns the user ID who last edited the translation.
     *
     * @return null|int
     */
    public function userId(): null|int;

    /**
     * Returns the workflow status.
     *
     * @return string
     */
    public function status(): string;

    /**
     * Returns the resource name.
     *
     * @return string
     */
    public function resourceName(): string;

    /**
     * Returns the locale of the translation.
     *
     * @return string
     */
    public function resourceLocale(): string;

    /**
     * Returns the resource group.
     *
     * @return string
     */
    public function resourceGroup(): string;

    /**
     * Returns the resource priority.
     *
     * @return int
     */
    public function resourcePriority(): int;

    /**
     * Returns the resource filename.
     *
     * @return null|string
     */
    public function resourceFilename(): null|string;

    /**
     * Returns the message identifier.
     *
     * @return string
     */
    public function message(): string;

    /**
     * Returns the translation value.
     *
     * @return string
     */
    public function translation(): string;

    /**
     * Returns whether the translation is missing.
     *
     * @return bool
     */
    public function isTranslationMissing(): bool;

    /**
     * Returns who last translated the entry.
     *
     * @return null|string
     */
    public function translatedBy(): null|string;

    /**
     * Returns the original translation before override.
     *
     * @return null|string
     */
    public function originTranslation(): null|string;

    /**
     * Returns who provided the original translation.
     *
     * @return null|string
     */
    public function originTranslatedBy(): null|string;

    /**
     * Returns whether the translation was modified.
     *
     * @return bool
     */
    public function isModified(): bool;

    /**
     * Returns translator notes.
     *
     * @return null|string
     */
    public function notes(): null|string;

    /**
     * Returns metadata.
     *
     * @return array
     */
    public function meta(): array;

    /**
     * Returns the creation timestamp.
     *
     * @return null|DateTimeInterface
     */
    public function createdAt(): null|DateTimeInterface;

    /**
     * Returns the last update timestamp.
     *
     * @return null|DateTimeInterface
     */
    public function updatedAt(): null|DateTimeInterface;
}