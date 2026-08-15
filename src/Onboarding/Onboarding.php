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

use Tobento\App\Translation\Web\SavedTranslation;
use Tobento\App\Translation\Web\SavedTranslations;
use Tobento\App\Translation\Web\SavedTranslationStatus;
use Tobento\App\Translation\Web\TranslationEntityInterface;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Service\Iterable\ChunkIterator;
use Tobento\Service\MachineTranslator\Exception\TranslateException;
use Tobento\Service\MachineTranslator\MachineTranslatorInterface;
use Tobento\Service\MachineTranslator\NullMachineTranslator;

class Onboarding implements OnboardingInterface
{
    /**
     * Create a new instance.
     *
     * @param TranslationRepositoryInterface $translationRepository
     * @param null|MachineTranslatorInterface $machineTranslator
     */
    public function __construct(
        protected TranslationRepositoryInterface $translationRepository,
        protected null|MachineTranslatorInterface $machineTranslator = null,
        protected int $autoTranslateInChunksOf = 20,
    ) {}
    
    /**
     * Returns the machine translator used for auto-translation, if available.
     *
     * @return null|MachineTranslatorInterface
     */
    public function machineTranslator(): null|MachineTranslatorInterface
    {
        return $this->machineTranslator;
    }
    
    /**
     * Indicates whether auto-translation is supported.
     *
     * @return bool
     */
    public function supportsAutoTranslate(): bool
    {
        return $this->machineTranslator !== null
            && ! $this->machineTranslator instanceof NullMachineTranslator;
    }
    
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
    ): CreatedTranslationsInterface {
        
        $items = $this->translationRepository->findAllBy(
            appId: $appId,
            locale: $this->translationRepository->messageLocale(),
        );
        
        $savedTranslations = new SavedTranslations();
        
        foreach ($items as $t) {
            $saved = $this->translationRepository->saveTranslation(
                appId: $appId,
                resourceName: $t->resourceName(),
                resourceLocale: $locale,
                resourceGroup: $t->resourceGroup(),
                resourcePriority: $t->resourcePriority(),
                resourceFilename: $t->resourceFilename(),
                message: $t->message(),
                translation: '', // marks entry as missing, eligible for auto-translation
                translatedBy: $t->translatedBy(),
            );

            $savedTranslations->add($saved);
        }
        
        return new CreatedTranslations(
            locale: $locale,
            appId: $appId,
            saved: $savedTranslations,
        );
    }
    
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
    ): AutoTranslatedTranslationsInterface {
        
        $translated = $this->autoTranslateEntriesBy(
            where: ['resource_locale' => $locale, 'app_id' => $appId],
            orderBy: [],
            limit: [],
        );
        
        return new AutoTranslatedTranslations(
            locale: $locale,
            appId: $appId,
            saved: $translated->saved(),
        );
    }
    
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
    ): AutoTranslatedTranslationsInterface {

        if (!$this->supportsAutoTranslate()) {
            return new AutoTranslatedTranslations(
                locale: '',
                appId: '',
                saved: new SavedTranslations(),
            );
        }

        $items = $this->translationRepository->findAll(
            where: $where,
            orderBy: $orderBy,
            limit: $limit,
        );

        $savedTranslations = new SavedTranslations();

        $iterator = new ChunkIterator(
            iterable: $items,
            chunkLength: $this->autoTranslateInChunksOf,
        );

        foreach ($iterator as $chunk) {

            // Filter translations
            $missing = array_filter($chunk, fn (TranslationEntityInterface $t): bool => $t->isTranslationMissing());
            $notMissing = array_filter($chunk, fn (TranslationEntityInterface $t): bool => !$t->isTranslationMissing());

            // Mark all non-missing as skipped
            foreach ($notMissing as $t) {
                $savedTranslations->add(new SavedTranslation(
                    status: SavedTranslationStatus::SKIPPED,
                    entity: $t,
                    skippedReason: 'Translation already exists',
                ));
            }

            // Nothing to translate in this chunk
            if (empty($missing)) {
                continue;
            }
            
            // Group missing entries by locale
            $groups = [];
            foreach ($missing as $t) {
                $groups[$t->resourceLocale()][] = $t;
            }

            foreach ($groups as $locale => $entries) {
                // Extract texts
                $texts = array_map(fn($t) => $t->message(), $entries);

                try {
                    $translatedTexts = $this->machineTranslator->translateMany(
                        texts: $texts,
                        locale: $locale,
                    );
                } catch (TranslateException $e) {
                    // Mark all as skipped due to batch failure
                    foreach ($entries as $t) {
                        $savedTranslations->add(new SavedTranslation(
                            status: SavedTranslationStatus::SKIPPED,
                            entity: $t,
                            skippedReason: 'Machine translation failed: '.$e->getMessage(),
                        ));
                    }
                    continue;
                }

                // Save each translated entry
                foreach ($entries as $index => $t) {
                    $translated = $translatedTexts[$index] ?? null;

                    if ($translated === null) {
                        $savedTranslations->add(new SavedTranslation(
                            status: SavedTranslationStatus::SKIPPED,
                            entity: $t,
                            skippedReason: 'Missing translated text in batch result',
                        ));
                        continue;
                    }

                    $saved = $this->translationRepository->saveTranslation(
                        appId: $t->appId(),
                        resourceName: $t->resourceName(),
                        resourceLocale: $locale,
                        resourceGroup: $t->resourceGroup(),
                        resourcePriority: $t->resourcePriority(),
                        resourceFilename: $t->resourceFilename(),
                        message: $t->message(),
                        translation: $translated,
                        translatedBy: $this->machineTranslator->name(),
                    );

                    $savedTranslations->add($saved);
                }
            }
        }

        return new AutoTranslatedTranslations(
            locale: '',
            appId: '',
            saved: $savedTranslations,
        );
    }

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
    ): PublishedTranslationsInterface {
        
        $savedTranslations = $this->translationRepository->publishTranslations(
            appId: $appId,
            resourceLocale: $locale,
        );
        
        return new PublishedTranslations(
            locale: $locale,
            appId: $appId,
            saved: $savedTranslations,
        );
    }
}