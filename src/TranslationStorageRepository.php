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

use RuntimeException;
use Tobento\Service\Repository\Storage\Column\ColumnsInterface;
use Tobento\Service\Repository\Storage\Column\ColumnInterface;
use Tobento\Service\Repository\Storage\Column;
use Tobento\Service\Repository\Storage\StorageEntityFactoryInterface;
use Tobento\Service\Repository\Storage\StorageRepository;
use Tobento\Service\Storage\Items;
use Tobento\Service\Storage\ItemsInterface;
use Tobento\Service\Storage\StorageInterface;
use Throwable;

/**
 * TranslationStorageRepository
 *
 * Storage-backed repository for translation records.
 *
 * All entity-returning methods MUST return instances of
 * TranslationEntityInterface or iterables containing
 * TranslationEntityInterface objects.
 */
class TranslationStorageRepository extends StorageRepository implements TranslationRepositoryInterface
{
    /**
     * Create a new instance.
     *
     * @param StorageInterface $storage
     * @param string $table
     * @param TranslationEntityFactory $translationEntityFactory
     * @param string $messageLocale
     */
    public function __construct(
        protected StorageInterface $storage,
        protected string $table,
        protected TranslationEntityFactory $translationEntityFactory,
        protected string $messageLocale = 'en',
    ) {
        parent::__construct(storage: $storage, table: $table, columns: null, entityFactory: $translationEntityFactory);
    }

    /**
     * Returns the message locale.
     *
     * @return string
     */
    public function messageLocale(): string
    {
        return $this->messageLocale;
    }
    
    /**
     * Returns a new instance with the given locale.
     *
     * @param string $locale
     * @return static
     */
    public function withMessageLocale(string $locale): static
    {
        $new = clone $this;
        $new->messageLocale = $locale;
        return $new;
    }
    
    /**
     * Returns the configured columns.
     *
     * @return iterable<ColumnInterface>|ColumnsInterface
     */
    protected function configureColumns(): iterable|ColumnsInterface
    {
        return [
            // Primary identifier for the translation record
            new Column\Id(),

            // The app this translation belongs to (root, backend, frontend, api, etc.)
            new Column\Text('app_id')->type(index: ['name' => 'app_id', 'column' => 'app_id']),

            // Backend user who last edited the translation (nullable for AI/system edits)
            new Column\Integer(name: 'user_id', type: 'int')->type(unsigned: true, nullable: true),

            // Workflow status (e.g. active, pending_review, disabled)
            new Column\Text('status')->type(length: 100, index: ['name' => 'status', 'column' => 'status']),

            // Name of the original resource (*, shop, etc.)
            new Column\Text('resource_name'),

            // Locale of the resource (e.g. en, de-CH, fr)
            new Column\Text('resource_locale')->type(length: 5),

            // Group/category inside the resource (e.g. frontend, validation)
            new Column\Text('resource_group')->type(length: 100),

            // Priority of the resource (higher overrides lower)
            new Column\Integer('resource_priority')->type(length: 11, unsigned: true, nullable: false, default: 0),
            
            // The filename this translation belongs to (only for FileResources strategy)
            new Column\Text('resource_filename')->type(length: 150, nullable: true),
            
            // Message identifier (can be a real sentence, keyword key, or pluralization pattern)
            new Column\Text(name: 'message', type: 'text'),

            // Whether the translation is missing (empty, identical to the origin locale value, or not provided)
            new Column\Boolean('is_translation_missing')->type(default: false),
            
            // Current translation value (editable)
            new Column\Text(name: 'translation', type: 'text'),

            // Who last edited the translation (human or AI)
            new Column\Text('translated_by')->type(length: 100),

            // Original translation value before override
            new Column\Text(name: 'origin_translation', type: 'text'),

            // Who provided the original translation (resource, AI, system)
            new Column\Text('origin_translated_by')->type(length: 100),
            
            // Whether the translation is modified
            new Column\Boolean('is_modified')->type(default: false, index: ['name' => 'is_modified', 'column' => 'is_modified']),

            // Optional translator notes or context
            new Column\Text(name: 'notes', type: 'text'),

            // Additional metadata (AI confidence, suggestions, flags, etc.)
            new Column\Json('meta'),

            // Creation timestamp (required for purge commands)
            new Column\Datetime(name: 'created_at', type: 'timestamp')->autoCreate(),

            // Auto-updated timestamp for last modification
            new Column\Datetime('date_updated')->autoUpdate(),
        ];
    }
    
    /**
     * Creates or updates a translation entry using the provided parameters.
     *
     * If an entry already exists, it is updated only when the stored translation
     * matches the incoming one (to avoid overwriting human‑edited translations).
     * Otherwise the update is skipped and marked as a human override.
     *
     * If no entry exists, a new translation record is created with the given metadata.
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
    ): SavedTranslationInterface {
        // Check if translation already exists
        $existing = $this->findOne(where: [
            'app_id' => $appId,
            'resource_name' => $resourceName,
            'resource_locale' => $resourceLocale,
            'resource_group' => $resourceGroup,
            'message' => $message,
        ]);
        
        if ($existing) {
            // If the existing translation is NOT the same as the imported one,
            // then a human has overridden it, DO NOT IMPORT.
            if (!$existing->isTranslationMissing() && $existing->translation() !== $translation) {
                return new SavedTranslation(
                    status: SavedTranslationStatus::SKIPPED,
                    entity: $existing,
                    skippedReason: 'Existing translation differs - human override detected',
                );
            }
            
            // Otherwise, safe to update origin metadata
            $updated = $this->updateById($existing->id(), [
                'translation' => $translation,
                'translated_by' => $translatedBy,
                'is_translation_missing' => $this->isMissing($existing->message(), $translation, $resourceLocale),
            ]);
            
            return new SavedTranslation(
                status: SavedTranslationStatus::UPDATED,
                entity: $updated,
            );
        }

        // Create new entry
        $created = $this->create([
            'app_id' => $appId,
            'resource_name' => $resourceName,
            'resource_locale' => $resourceLocale,
            'resource_group' => $resourceGroup,
            'resource_priority' => $resourcePriority,
            'resource_filename' => $resourceFilename,
            'message' => $message,
            'translation' => $translation,
            'origin_translation' => $translation,
            'translated_by' => $translatedBy,
            'origin_translated_by' => $translatedBy,
            'is_translation_missing' => $this->isMissing($message, $translation, $resourceLocale),
            'status' => 'imported',
            'user_id' => null,
        ]);
        
        return new SavedTranslation(
            status: SavedTranslationStatus::CREATED,
            entity: $created,
        );
    }
    
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
    ): SavedTranslationsInterface {
        
        $where = ['app_id' => $appId];
        
        if ($resourceLocale !== null) {
            $where['resource_locale'] = $resourceLocale;
        }
        
        if ($resourceName !== null) {
            $where['resource_name'] = $resourceName;
        }        
        
        $updatedItems = $this->update(
            where: $where,
            attributes: [
                'status' => 'published',
            ],
        );
        
        $savedTranslations = new SavedTranslations();
        
        foreach ($updatedItems as $updated) {
            $savedTranslations->add(new SavedTranslation(
                status: SavedTranslationStatus::UPDATED,
                entity: $updated,
            ));
        }
        
        return $savedTranslations;
    }
    
    /**
     * Returns all translations, optionally filtered by appId and locale.
     *
     * @param string|null $appId App ID to filter by, or null for all app IDs.
     * @param string|null $locale Locale to filter by, or null for all locales.
     * @return ItemsInterface Found translation records.
     */
    public function findAllBy(null|string $appId = null, null|string $locale = null): ItemsInterface
    {
        $where = [];

        if ($appId !== null) {
            $where['app_id'] = $appId;
        }
        
        if ($locale !== null) {
            $where['resource_locale'] = $locale;
        }
        
        $result = $this->findAll(where: $where);

        if (!$result instanceof ItemsInterface) {
            throw new RuntimeException(
                'Storage returned invalid type: expected ItemsInterface, got ' . get_debug_type($result)
            );
        }

        return $result;
    }
    
    /**
     * Returns all published translations, optionally filtered by appId and locale.
     *
     * @param string|null $appId App ID to filter by, or null for all app IDs.
     * @param string|null $locale Locale to filter by, or null for all locales.
     * @return ItemsInterface Published translation records.
     */
    public function findAllPublished(null|string $appId = null, null|string $locale = null): ItemsInterface
    {
        $where = [
            'status' => 'published',
            'is_translation_missing' => ['!=' => '1'],
        ];

        if ($appId !== null) {
            $where['app_id'] = $appId;
        }
        
        if ($locale !== null) {
            $where['resource_locale'] = $locale;
        }

        $result = $this->findAll(where: $where);

        if (!$result instanceof ItemsInterface) {
            throw new RuntimeException(
                'Storage returned invalid type: expected ItemsInterface, got ' . get_debug_type($result)
            );
        }

        return $result;
    }
    
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
    ): ItemsInterface {
        $where = [
            'is_modified' => '1',
        ];
        
        if ($appId !== null) {
            $where['app_id'] = $appId;
        }
        
        if ($status !== null) {
            $where['status'] = $status;
        }
        
        if ($locale !== null) {
            $where['resource_locale'] = $locale;
        }        

        $result = $this->findAll(where: $where);

        if (!$result instanceof ItemsInterface) {
            throw new RuntimeException(
                'Storage returned invalid type: expected ItemsInterface, got ' . get_debug_type($result)
            );
        }

        return $result;
    }
    
    /**
     * Resets the modified state on the given translations and returns them.
     *
     * @param iterable<TranslationEntityInterface> $translations
     * @return ItemsInterface
     */
    public function resetModified(iterable $translations): ItemsInterface
    {
        $items = new Items($translations);

        if ($items->count() === 0) {
            return new Items([]);
        }
        
        $updated = $this->update(
            where: ['id' => ['in' => $items->column('id')]],
            attributes: ['is_modified' => false],
        );
        
        return new Items($updated);
    }

    /**
     * Returns distinct values for the given column as a key/value array.
     *
     * @param string $column The column name to extract unique values from.
     * @return array<string, string> The distinct values indexed by themselves.
     */
    public function distinctValues(string $column): array
    {
        $values = $this->query()
            ->select($column)
            ->groupBy($column)
            ->column($column);

        $unique = array_unique($values->all());

        return array_combine($unique, $unique);
    }
    
    /**
     * Determines whether a translation is considered missing for the given message and locale.
     *
     * A translation is treated as missing when:
     * - the translation value is an empty string, or
     * - the translation is identical to the original message for a locale other than the message locale.
     *
     * The original message locale is allowed to have a translation identical to the message,
     * since it represents the source text rather than a translated value.
     *
     * @param string $message The original message key or source text.
     * @param string $translation The stored translation value.
     * @param string $locale The locale for which the translation is evaluated.
     * @return bool True if the translation is considered missing.
     */
    protected function isMissing(string $message, string $translation, string $locale): bool
    {
        if ($translation === '') {
            return true;
        }

        if ($translation === $message && $locale === $this->messageLocale) {
            return false;
        }

        return $translation === $message;
    }
}