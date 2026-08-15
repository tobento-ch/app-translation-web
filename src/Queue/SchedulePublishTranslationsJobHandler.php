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

namespace Tobento\App\Translation\Web\Queue;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;
use Tobento\Apps\AppsInterface;
use Tobento\Apps\Exception\AppNotFoundException;
use Tobento\Service\Queue\JobHandlerInterface;
use Tobento\Service\Queue\JobInterface;

/**
 * Handles queued translation‑publish scheduling jobs.
 *
 * This job is dispatched whenever a translation is modified. It ensures that
 * the correct application (including sub‑apps in multi‑app environments) is
 * fully booted before delegating to the active TranslationsPublishInterface
 * strategy. The strategy then decides how the publish should proceed
 * (e.g., queueing file‑generation jobs for FileResources).
 *
 * This handler guarantees that publish operations run in a complete and
 * consistent application context, something that cannot be ensured during
 * HTTP request handling.
 */
class SchedulePublishTranslationsJobHandler implements JobHandlerInterface
{
    public function __construct(
        protected AppInterface $app,
    ) {}

    public function handleJob(JobInterface $job): void
    {
        $payload = $job->getPayload();

        $appId = $payload['app_id'] ?? null;
        $translationId = $payload['translation_id'] ?? null;

        if ($appId === null || $translationId === null) {
            return;
        }

        // Single-app mode
        if (! $this->app->has(AppsInterface::class)) {

            // Only run if the job is for this app
            if ($this->app->id() !== $appId) {
                // Optionally log
                return;
            }

            $this->scheduleForApp($this->app);
            return;
        }

        // Multi-app mode
        $apps = $this->app->get(AppsInterface::class);

        // Boot all apps first (required for sub-apps)
        foreach ($apps->all() as $appBoot) {
            $appBoot->app()->booting();
        }

        try {
            $application = $apps->get($appId)->app();
        } catch (AppNotFoundException $e) {
            // Optionally log
            return;
        }

        // Switch context to the target app
        $apps->bootingApp($application);

        $this->scheduleForApp($application);

        // Restore main app context
        $apps->bootingApp($this->app);
    }

    protected function scheduleForApp(AppInterface $app): void
    {
        if (! $app->has(TranslationsPublishInterface::class)) {
            return;
        }
        
        $app->get(TranslationsPublishInterface::class)->schedulePublish($app);
    }
}