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
use Tobento\App\Testing\Database\RefreshDatabases;
use Tobento\App\Translation\Web\Queue\CreateForLocaleJobHandler;
use Tobento\App\User\UserRepositoryInterface;
use Tobento\Service\Notifier\Test\FakeNotifier;
use Tobento\Service\Notifier\ChannelMessagesInterface;
use Tobento\Service\Notifier\Notification;
use Tobento\Service\Queue\Job;

class CreateForLocaleJobHandlerTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;

    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');

        // Boot translation web (includes real Onboarding)
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);

        return $app;
    }

    public function testDoesNothingIfLocaleOrAppIdMissing()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        $handler = new CreateForLocaleJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $app->get(UserRepositoryInterface::class),
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(name: 'create', payload: [
            'locale' => null,
            'app_id' => 'root',
        ]);

        $handler->handleJob($job);

        $fakeNotifier->assertNothingSent();
    }

    public function testCreatesEntriesAndNotifiesUser()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        // Create user
        $users = $app->get(UserRepositoryInterface::class);
        $user = $users->create(['email' => 'test@example.com']);

        $handler = new CreateForLocaleJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $users,
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(name: 'create', payload: [
            'locale' => 'fr',
            'app_id' => 'root',
            'user_id' => $user->id(),
        ]);

        $handler->handleJob($job);

        $fakeNotifier->assertSent(Notification::class, function(ChannelMessagesInterface $messages) {
            $content = $messages->notification()->getContent();

            return str_contains($content, 'Created records')
                && str_contains($content, 'Created:')
                && str_contains($content, 'Skipped:');
        });
    }

    public function testAutoTranslateIncludedIfEnabled()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        $users = $app->get(UserRepositoryInterface::class);
        $user = $users->create(['email' => 'test@example.com']);

        $handler = new CreateForLocaleJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $users,
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(name: 'create', payload: [
            'locale' => 'fr',
            'app_id' => 'root',
            'user_id' => $user->id(),
            'auto_translate' => true,
        ]);

        $handler->handleJob($job);

        $fakeNotifier->assertSent(Notification::class, function(ChannelMessagesInterface $messages) {
            $content = $messages->notification()->getContent();

            return str_contains($content, 'Auto-translated records')
                && str_contains($content, 'Translated:')
                && str_contains($content, 'Skipped:');
        });
    }

    public function testPublishIncludedIfEnabled()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        $users = $app->get(UserRepositoryInterface::class);
        $user = $users->create(['email' => 'test@example.com']);

        $handler = new CreateForLocaleJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $users,
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(name: 'create', payload: [
            'locale' => 'fr',
            'app_id' => 'root',
            'user_id' => $user->id(),
            'publish' => true,
        ]);

        $handler->handleJob($job);

        $fakeNotifier->assertSent(Notification::class, function(ChannelMessagesInterface $messages) {
            return str_contains($messages->notification()->getContent(), 'Published records');
        });
    }

    public function testUserNotFoundMeansNoNotification()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        $handler = new CreateForLocaleJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $app->get(UserRepositoryInterface::class),
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(name: 'create', payload: [
            'locale' => 'fr',
            'app_id' => 'root',
            'user_id' => 9999,
        ]);

        $handler->handleJob($job);

        $fakeNotifier->assertNothingSent();
    }
}