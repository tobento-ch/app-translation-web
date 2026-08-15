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

namespace Tobento\App\Translation\Web\Test\Feature\Queue;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Queue\SchedulePublishTranslationsJobHandler;
use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;
use Tobento\App\Translation\Web\Test\FakePublishStrategy;
use Tobento\Apps\AppsInterface;
use Tobento\Service\Queue\Job;

class SchedulePublishTranslationsJobHandlerTest extends \Tobento\App\Testing\TestCase
{
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        $app->boot(\Tobento\App\Translation\Web\Test\App\Backend::class);
        $app->boot(\Tobento\App\Translation\Web\Test\App\Frontend::class);
        return $app;
    }

    public function testDoesNothingIfPayloadMissing()
    {
        $app = $this->getApp();

        $strategy = new FakePublishStrategy();
        $app->set(\Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface::class, $strategy);

        $handler = new SchedulePublishTranslationsJobHandler($app);

        $job = new Job('publish', payload: []);

        $handler->handleJob($job);

        $this->assertSame([], $strategy->scheduled);
    }

    public function testSingleAppModeIgnoresOtherApp()
    {
        $app = $this->getApp();

        $strategy = new FakePublishStrategy();
        $app->set(TranslationsPublishInterface::class, $strategy);

        $handler = new SchedulePublishTranslationsJobHandler($app);

        $job = new Job('publish', payload: [
            'app_id' => 'other-app',
            'translation_id' => 1,
        ]);

        $handler->handleJob($job);

        $this->assertSame([], $strategy->scheduled);
    }

    public function testSingleAppModePublishesForMatchingApp()
    {
        $app = $this->getApp();

        $strategy = new FakePublishStrategy();
        $app->set(TranslationsPublishInterface::class, $strategy);

        $handler = new SchedulePublishTranslationsJobHandler($app);

        $job = new Job('publish', payload: [
            'app_id' => $app->id(),
            'translation_id' => 1,
        ]);

        $handler->handleJob($job);

        $this->assertSame([$app->id()], $strategy->scheduled);
    }

    public function testMultiAppModePublishesForCorrectSubApp()
    {
        $app = $this->bootingApp();
        $apps = $app->get(AppsInterface::class);

        // Retrieve real backend app
        $backend = $apps->get('backend')->app();
        $backend->booting();

        // Bind strategy only to backend
        $strategy = new FakePublishStrategy();
        $backend->set(TranslationsPublishInterface::class, $strategy)->prototype();

        $handler = new SchedulePublishTranslationsJobHandler($app);

        $job = new Job('publish', payload: [
            'app_id' => 'backend',
            'translation_id' => 123,
        ]);

        $handler->handleJob($job);

        $this->assertSame(['backend'], $strategy->scheduled);
    }

    public function testMultiAppModeIgnoresUnknownApp()
    {
        $app = $this->bootingApp();
        $apps = $app->get(AppsInterface::class);

        $this->assertFalse($apps->has('unknown-app'));

        $strategy = new FakePublishStrategy();
        $app->set(TranslationsPublishInterface::class, $strategy)->prototype();

        $handler = new SchedulePublishTranslationsJobHandler($app);

        $job = new Job('publish', payload: [
            'app_id' => 'unknown-app',
            'translation_id' => 1,
        ]);

        $handler->handleJob($job);

        $this->assertSame([], $strategy->scheduled);
    }
}