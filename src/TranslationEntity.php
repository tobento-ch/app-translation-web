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

/**
 * TranslationEntity
 *
 * Represents a single translation record stored in the repository.
 */
class TranslationEntity implements TranslationEntityInterface
{
    /**
     * Create a new TranslationEntity instance.
     *
     * @param array $attributes The raw translation attributes.
     */
    public function __construct(
        protected array $attributes = [],
    ) {}

    /**
     * Returns all raw attributes.
     *
     * @return array
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Returns the translation ID.
     *
     * @return int
     */
    public function id(): int
    {
        $id = $this->attributes['id'] ?? 0;
        return is_int($id) ? $id : 0;
    }

    /**
     * Returns the application identifier.
     *
     * @return string
     */
    public function appId(): string
    {
        $value = $this->attributes['app_id'] ?? '';
        return is_string($value) ? $value : '';
    }

    /**
     * Returns the user ID who last edited the translation.
     *
     * @return null|int
     */
    public function userId(): null|int
    {
        $value = $this->attributes['user_id'] ?? null;
        return is_int($value) ? $value : null;
    }

    /**
     * Returns the workflow status.
     *
     * @return string
     */
    public function status(): string
    {
        $value = $this->attributes['status'] ?? '';
        return is_string($value) ? $value : '';
    }

    /**
     * Returns the resource name.
     *
     * @return string
     */
    public function resourceName(): string
    {
        $value = $this->attributes['resource_name'] ?? '';
        return is_string($value) ? $value : '';
    }

    /**
     * Returns the locale of the translation.
     *
     * @return string
     */
    public function resourceLocale(): string
    {
        $value = $this->attributes['resource_locale'] ?? '';
        return is_string($value) ? $value : '';
    }

    /**
     * Returns the resource group.
     *
     * @return string
     */
    public function resourceGroup(): string
    {
        $value = $this->attributes['resource_group'] ?? '';
        return is_string($value) ? $value : '';
    }

    /**
     * Returns the resource priority.
     *
     * @return int
     */
    public function resourcePriority(): int
    {
        $value = $this->attributes['resource_priority'] ?? 0;
        return is_int($value) ? $value : (int)$value;
    }

    /**
     * Returns the resource filename.
     *
     * @return null|string
     */
    public function resourceFilename(): null|string
    {
        $value = $this->attributes['resource_filename'] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Returns the message identifier.
     *
     * @return string
     */
    public function message(): string
    {
        $value = $this->attributes['message'] ?? '';
        return is_string($value) ? $value : '';
    }

    /**
     * Returns the translation value.
     *
     * @return string
     */
    public function translation(): string
    {
        $value = $this->attributes['translation'] ?? '';
        return is_string($value) ? $value : '';
    }

    /**
     * Returns whether the translation is missing.
     *
     * @return bool
     */
    public function isTranslationMissing(): bool
    {
        $value = $this->attributes['is_translation_missing'] ?? false;
        return is_bool($value) ? $value : (bool)$value;
    }

    /**
     * Returns who last translated the entry.
     *
     * @return null|string
     */
    public function translatedBy(): null|string
    {
        $value = $this->attributes['translated_by'] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Returns the original translation before override.
     *
     * @return null|string
     */
    public function originTranslation(): null|string
    {
        $value = $this->attributes['origin_translation'] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Returns who provided the original translation.
     *
     * @return null|string
     */
    public function originTranslatedBy(): null|string
    {
        $value = $this->attributes['origin_translated_by'] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Returns whether the translation was modified.
     *
     * @return bool
     */
    public function isModified(): bool
    {
        $value = $this->attributes['is_modified'] ?? false;
        return is_bool($value) ? $value : (bool)$value;
    }

    /**
     * Returns translator notes.
     *
     * @return null|string
     */
    public function notes(): null|string
    {
        $value = $this->attributes['notes'] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Returns metadata.
     *
     * @return array
     */
    public function meta(): array
    {
        $value = $this->attributes['meta'] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * Returns the creation timestamp.
     *
     * @return null|DateTimeInterface
     */
    public function createdAt(): null|DateTimeInterface
    {
        $value = $this->attributes['created_at'] ?? null;

        return $value instanceof DateTimeInterface ? $value : null;
    }

    /**
     * Returns the last update timestamp.
     *
     * @return null|DateTimeInterface
     */
    public function updatedAt(): null|DateTimeInterface
    {
        $value = $this->attributes['date_updated'] ?? null;

        return $value instanceof DateTimeInterface ? $value : null;
    }
    
    /**
     * Object to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'app_id' => $this->appId(),
            'user_id' => $this->userId(),
            'status' => $this->status(),
            'resource_name' => $this->resourceName(),
            'resource_locale' => $this->resourceLocale(),
            'resource_group' => $this->resourceGroup(),
            'resource_priority' => $this->resourcePriority(),
            'resource_filename' => $this->resourceFilename(),
            'message' => $this->message(),
            'translation' => $this->translation(),
            'is_translation_missing' => $this->isTranslationMissing(),
            'translated_by' => $this->translatedBy(),
            'origin_translation' => $this->originTranslation(),
            'origin_translated_by' => $this->originTranslatedBy(),
            'is_modified' => $this->isModified(),
            'notes' => $this->notes(),
            'meta' => $this->meta(),
            'created_at' => $this->createdAt(),
            'date_updated' => $this->updatedAt(),
        ];
    }
    
    /**
     * __get For array_column object support
     */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name];
    }

    /**
     * __isset For array_column object support
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }
}