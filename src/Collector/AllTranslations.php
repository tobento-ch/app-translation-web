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

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\SavedTranslations;
use Tobento\App\Translation\Web\SavedTranslationsInterface;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Apps\AppFinder;
use Tobento\Apps\AppsInterface;
use Tobento\Service\Translation\ResourceFile;
use Tobento\Service\Translation\ResourceInterface;
use Tobento\Service\Translation\LocaleAware;
use Tobento\Service\Translation\ResourcesAware;
use Tobento\Service\Translation\TranslatorInterface;
use function Tobento\App\Translation\trans;

class AllTranslations implements CollectorInterface
{
    /**
     * Create a new instance.
     *
     * @param string $id A unique identifier for this collector
     * @param string $name A human-readable name describing the collector
     * @param string $appId The application ID this collector should operate on
     * @param array<int, string> $collectOnly Only collect translations for these resource names (whitelist)
     * @param array<int, string> $collectExcept Do not collect translations for these resource names (blacklist)
     * @param array<int, string> $locales Locales to load; if empty, defaults to translator locale and fallbacks
     */
    final public function __construct(
        protected string $id,
        protected string $name,
        protected string $appId,
        protected array $collectOnly = [],
        protected array $collectExcept = [],
        protected array $locales = [],
    ) {}

    /**
     * Returns the collector id.
     *
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }
    
    /**
     * Returns the collector name.
     *
     * @return string
     */
    public function name(): string
    {
        return trans($this->name);
    }
    
    /**
     * Collect all translations for the given app.
     *
     * @param AppInterface $app
     * @return CollectedTranslationsInterface
     */
    public function collect(AppInterface $app): CollectedTranslationsInterface
    {
        $appFinder = new AppFinder(app: $app);
        $application = $appFinder->findByIdRecursive(id: $this->appId);
        
        if (is_null($application)) {
            return new CollectedTranslations(new SavedTranslations());
        }
        
        if (! $application->has(TranslatorInterface::class)) {
            $this->rebootApp($app);
            return new CollectedTranslations(new SavedTranslations());
        }
        
        $translator = $application->get(TranslatorInterface::class);
        
        if (! $translator instanceof ResourcesAware) {
            $this->rebootApp($app);
            return new CollectedTranslations(new SavedTranslations());
        }
        
        // Determine the locales to load:
        $locales = $this->locales;
        
        if (empty($locales) && $translator instanceof LocaleAware) {
            $locales = array_unique([
                $translator->getLocale(),
                ...array_values($translator->getLocaleFallbacks()),
            ]);
        }
        
        // IMPORTANT: translations are lazy loaded, so calling locales will load it
        $resources = $translator->resources()->locales($locales);
        
        // Collect:
        $translationRepository = $application->get(TranslationRepositoryInterface::class);
        $savedTranslations = new SavedTranslations();
        
        foreach ($resources->all() as $resource) {
            if ($this->shouldCollect($resource)) {
                $this->collectResource($resource, $savedTranslations, $translationRepository, $application);
            }
        }
        
        $this->rebootApp($app);
        
        return new CollectedTranslations($savedTranslations);
    }
    
    protected function rebootApp(AppInterface $app): void
    {
        if ($app->has(AppsInterface::class)) {
            $app->get(AppsInterface::class)->bootingApp($app);
        }
    }

    protected function collectResource(
        ResourceInterface $resource,
        SavedTranslationsInterface $savedTranslations,
        TranslationRepositoryInterface $translationRepository,
        AppInterface $app,
    ): void {
        $translations = $resource->translations();
        
        $resourceFilename = $resource instanceof ResourceFile
            ? $resource->file()->getBasename()
            : null;
        
        foreach($translations as $message => $translation) {
            $saved = $translationRepository->saveTranslation(
                appId: $app->id(),
                resourceName: $resource->name(),
                resourceLocale: $resource->locale(),
                resourceGroup: $resource->group() ?: '',
                resourcePriority: $resource->priority(),
                resourceFilename: $resourceFilename,
                message: $message,
                translation: $translation,
                translatedBy: $this->translatedByFor($resource),
            );
            
            $savedTranslations->add($saved);
        }
    }
    
    protected function shouldCollect(ResourceInterface $resource): bool
    {
        $name = $resource->name();

        // collectOnly mode
        if (!empty($this->collectOnly)) {
            return in_array($name, $this->collectOnly, true);
        }

        // collectExcept mode
        if (!empty($this->collectExcept)) {
            return !in_array($name, $this->collectExcept, true);
        }

        // default: collect everything
        return true;
    }

    protected function translatedByFor(ResourceInterface $resource): string
    {
        return match (true) {
            $resource instanceof \Tobento\Service\Translation\ResourceFile => 'system.file',
            $resource instanceof \Tobento\Service\Translation\Resource => 'system',
            default => 'resource',
        };
    }
}