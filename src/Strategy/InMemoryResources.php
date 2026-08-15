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

namespace Tobento\App\Translation\Web\Strategy;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Service\Translation\Resource;
use Tobento\Service\Translation\ResourceInterface;
use Tobento\Service\Translation\Resources;
use Tobento\Service\Translation\ResourcesAware;
use Tobento\Service\Translation\ResourcesInterface;
use Tobento\Service\Translation\TranslatorInterface;

/**
 * A no-operation translation publish strategy. Both publish methods
 * intentionally perform no actions.
 */
final class InMemoryResources implements TranslationsPublishInterface
{
    /**
     * Publishes all modified translations immediately using
     * the strategy's concrete publishing mechanism.
     *
     * @param AppInterface $app The application instance.
     * @return void
     */
    public function publish(AppInterface $app): void
    {
        $app->on(TranslatorInterface::class, function(TranslatorInterface $translator) use ($app): void {

            if (! $translator instanceof ResourcesAware) {
                return;    
            }
            
            $translator->resources()->add(
                $this->createRepositoryResources(
                    appId: $app->id(),
                    repository: $app->get(TranslationRepositoryInterface::class),
                )
            );
        });
    }

    /**
     * Schedules or triggers a publish operation, for example
     * by dispatching a queue job instead of publishing directly.
     *
     * @param AppInterface $app The application instance.
     * @return void
     */
    public function schedulePublish(AppInterface $app): void
    {
        //
    }

    /**
     * Creates a lazy-loading translation resources instance backed by
     * the given repository. The returned ResourcesInterface loads
     * published translations on demand for each requested locale.
     *
     * @param string $appId
     * @param TranslationRepositoryInterface $repository
     * @return ResourcesInterface
     */
    private function createRepositoryResources(
        string $appId,
        TranslationRepositoryInterface $repository
    ): ResourcesInterface {
        return new class($appId, $repository) extends Resources
        {
            public function __construct(
                private string $appId,
                private TranslationRepositoryInterface $repository,
            ) {}
            
            protected function createResources(array $locales): void
            {
                foreach ($locales as $locale) {

                    if (in_array($locale, $this->loadedLocales)) {
                        continue;
                    }

                    $this->loadedLocales[] = $locale;
                    
                    $translations = $this->repository->findAllPublished(appId: $this->appId, locale: $locale);

                    $groups = [];

                    foreach ($translations as $t) {
                        $resourceName = $t->resourceName();

                        // Initialize group entry if not exists
                        if (!isset($groups[$resourceName])) {
                            $groups[$resourceName] = [
                                'translations' => [],
                                'group' => $t->resourceGroup(),
                                'priority' => $t->resourcePriority(),
                            ];
                        }

                        // Add translation
                        $groups[$resourceName]['translations'][$t->message()] = $t->translation();
                    }

                    // Now build Resource objects
                    foreach ($groups as $name => $data) {
                        $this->add(new Resource(
                            name: $name,
                            locale: $locale,
                            translations: $data['translations'],
                            group: $data['group'] ?? '',
                            priority: $data['priority'] ?? 100000,
                        ));
                    }

                    // Load additional sources (same as FilesResources)
                    foreach ($this->sources as $resources) {
                        foreach ($resources->locale($locale)->all() as $resource) {
                            $this->add($resource);
                        }
                    }
                }
            }
        };
    }
}