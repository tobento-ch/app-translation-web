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
use Tobento\App\Translation\Web\Onboarding\AutoTranslatedTranslationsInterface;
use Tobento\App\Translation\Web\Queue\AutoTranslateJobHandler;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\App\User\UserRepositoryInterface;
use Tobento\Service\Notifier\ChannelMessagesInterface;
use Tobento\Service\Notifier\Notification;
use Tobento\Service\Notifier\Test\FakeNotifier;
use Tobento\Service\Queue\Job;
use Tobento\Service\Queue\JobInterface;

class AutoTranslateJobHandlerTest extends \Tobento\App\Testing\TestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    use \Tobento\App\Translation\Web\Test\Addon\MachineTranslatorAddon;

    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }

    public function testJobHandlerAutoTranslatesAndNotifiesUser()
    {
        $this->withMachineTranslator();

        $fakeNotifier = $this->fakeNotifier();
        
        $app = $this->bootingApp();
        
        $users = $app->get(UserRepositoryInterface::class);

        // Create a user who will receive the notification
        $user = $users->create([
            'email' => 'test@example.com',
            'name'  => 'Test User',
        ]);

        // Seed translations
        $repo = $app->get(TranslationRepositoryInterface::class);

        $repo->create([
            'app_id' => 'root',
            'resource_name' => 'messages',
            'resource_locale' => 'fr',
            'resource_group' => 'default',
            'resource_priority' => 0,
            'resource_filename' => 'messages.php',
            'message' => 'hello',
            'translation' => '',
            'is_translation_missing' => true,
        ]);

        $repo->create([
            'app_id' => 'root',
            'resource_name' => 'messages',
            'resource_locale' => 'en',
            'resource_group' => 'default',
            'resource_priority' => 0,
            'resource_filename' => 'messages.php',
            'message' => 'hello',
            'translation' => 'Hello',
            'is_translation_missing' => false,
        ]);
        
        $repo->create([
            'app_id' => 'another',
            'resource_name' => 'messages',
            'resource_locale' => 'en',
            'resource_group' => 'default',
            'resource_priority' => 0,
            'resource_filename' => 'messages.php',
            'message' => 'hello',
            'translation' => 'Hello',
            'is_translation_missing' => false,
        ]);

        // Create job payload
        $job = new Job(
            name: 'sample',
            payload: [
                'user_id' => $user->id(),
                'filters' => [
                    'where' => ['app_id' => 'root'],
                    'orderBy' => [],
                ],
            ],
        );

        // Run handler
        $handler = new AutoTranslateJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $users,
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $handler->handleJob($job);

        // Assert translations updated
        $updated = $repo->findAll(where: ['resource_locale' => 'fr']);
        $entity = $updated->first();
        $this->assertSame('Test: hello', $entity->translation());

        // Assert notification sent
        $fakeNotifier->assertSent(Notification::class, static function(ChannelMessagesInterface $messages) use ($user): bool {
            $notification = $messages->notification();
            $recipient = $messages->recipient();
            
            return $recipient->user()->id() === $user->id()
                && str_contains($notification->getSubject(), 'Auto‑translation completed')
                && str_contains($notification->getContent(), 'Translated: 1')
                && str_contains($notification->getContent(), 'Skipped: 1');
        });
    }
    
    public function testJobHandlerDoesNotNotifyWithoutUserId()
    {
        $this->withMachineTranslator();

        $fakeNotifier = $this->fakeNotifier();
        $app = $this->bootingApp();

        $repo = $app->get(TranslationRepositoryInterface::class);

        // Seed one missing translation
        $repo->create([
            'app_id' => 'root',
            'resource_name' => 'messages',
            'resource_locale' => 'fr',
            'resource_group' => 'default',
            'resource_priority' => 0,
            'resource_filename' => 'messages.php',
            'message' => 'hello',
            'translation' => '',
            'is_translation_missing' => true,
        ]);

        // Job payload WITHOUT user_id
        $job = new Job(
            name: 'sample',
            payload: [
                'filters' => [
                    'where' => ['app_id' => 'root'],
                    'orderBy' => [],
                ],
            ],
        );

        $handler = new AutoTranslateJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $app->get(UserRepositoryInterface::class),
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $handler->handleJob($job);

        // Assert: translation updated
        $updated = $repo->findAll(where: ['resource_locale' => 'fr'])->first();
        $this->assertSame('Test: hello', $updated->translation());

        // Assert: NO notification sent
        $fakeNotifier->assertNothingSent();
    }
    
    public function testJobHandlerDoesNotNotifyIfUserNotFound()
    {
        $this->withMachineTranslator();

        $fakeNotifier = $this->fakeNotifier();
        $app = $this->bootingApp();

        $repo = $app->get(TranslationRepositoryInterface::class);

        // Seed missing translation
        $repo->create([
            'app_id' => 'root',
            'resource_name' => 'messages',
            'resource_locale' => 'fr',
            'resource_group' => 'default',
            'resource_priority' => 0,
            'resource_filename' => 'messages.php',
            'message' => 'hello',
            'translation' => '',
            'is_translation_missing' => true,
        ]);

        // Job payload with NON‑EXISTENT user_id
        $job = new Job(
            name: 'sample',
            payload: [
                'user_id' => 9999,
                'filters' => [
                    'where' => ['app_id' => 'root'],
                    'orderBy' => [],
                ],
            ],
        );

        $handler = new AutoTranslateJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $app->get(UserRepositoryInterface::class),
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $handler->handleJob($job);

        // Assert: translation updated
        $updated = $repo->findAll(where: ['resource_locale' => 'fr'])->first();
        $this->assertSame('Test: hello', $updated->translation());

        // Assert: NO notification sent
        $fakeNotifier->assertNothingSent();
    }
    
    public function testJobHandlerHandlesNoMatchingEntries()
    {
        $this->withMachineTranslator();

        $fakeNotifier = $this->fakeNotifier();
        $app = $this->bootingApp();

        $users = $app->get(UserRepositoryInterface::class);

        // Create user
        $user = $users->create([
            'email' => 'test@example.com',
            'name'  => 'Test User',
        ]);

        // Seed translations in a different app_id
        $repo = $app->get(TranslationRepositoryInterface::class);

        $repo->create([
            'app_id' => 'another',
            'resource_name' => 'messages',
            'resource_locale' => 'fr',
            'resource_group' => 'default',
            'resource_priority' => 0,
            'resource_filename' => 'messages.php',
            'message' => 'hello',
            'translation' => '',
            'is_translation_missing' => true,
        ]);

        // Job filters that match NOTHING
        $job = new Job(
            name: 'sample',
            payload: [
                'user_id' => $user->id(),
                'filters' => [
                    'where' => ['app_id' => 'root'], // no entries match
                    'orderBy' => [],
                ],
            ],
        );

        $handler = new AutoTranslateJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $users,
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $handler->handleJob($job);

        // Assert: NO translation updated
        $updated = $repo->findAll(where: ['app_id' => 'another'])->first();
        $this->assertSame('', $updated->translation());

        // Assert: notification sent with 0/0
        $fakeNotifier->assertSent(Notification::class, static function(ChannelMessagesInterface $messages) use ($user): bool {
            $notification = $messages->notification();
            return str_contains($notification->getContent(), 'Translated: 0')
                && str_contains($notification->getContent(), 'Skipped: 0');
        });
    }
    
    public function testJobHandlerRespectsOrderByFilter()
    {
        $this->withMachineTranslator();

        $fakeNotifier = $this->fakeNotifier();
        $app = $this->bootingApp();

        $users = $app->get(UserRepositoryInterface::class);

        $user = $users->create([
            'email' => 'test@example.com',
            'name'  => 'Test User',
        ]);

        $repo = $app->get(TranslationRepositoryInterface::class);

        // Two missing translations, different messages
        $repo->create([
            'app_id' => 'root',
            'resource_name' => 'messages',
            'resource_locale' => 'fr',
            'resource_group' => 'default',
            'resource_priority' => 0,
            'resource_filename' => 'messages.php',
            'message' => 'b',
            'translation' => '',
            'is_translation_missing' => true,
        ]);

        $repo->create([
            'app_id' => 'root',
            'resource_name' => 'messages',
            'resource_locale' => 'fr',
            'resource_group' => 'default',
            'resource_priority' => 0,
            'resource_filename' => 'messages.php',
            'message' => 'a',
            'translation' => '',
            'is_translation_missing' => true,
        ]);

        // orderBy message ASC
        $job = new Job(
            name: 'sample',
            payload: [
                'user_id' => $user->id(),
                'filters' => [
                    'where' => ['app_id' => 'root'],
                    'orderBy' => ['message' => 'asc'],
                ],
            ],
        );

        $handler = new AutoTranslateJobHandler(
            onboarding: $app->get(\Tobento\App\Translation\Web\Onboarding\OnboardingInterface::class),
            userRepository: $users,
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $handler->handleJob($job);

        // Assert both translated
        $updated = $repo->findAll(where: ['resource_locale' => 'fr']);

        $translations = $updated->map(fn($e) => $e->translation());

        $this->assertContains('Test: a', $translations);
        $this->assertContains('Test: b', $translations);

        // Assert notification sent
        $fakeNotifier->assertSent(Notification::class);
    }
}