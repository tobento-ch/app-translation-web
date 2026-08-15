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
use Tobento\App\Translation\Web\Collector\Collectors;
use Tobento\App\Translation\Web\Queue\CollectTranslationsJobHandler;
use Tobento\App\Translation\Web\Test\Addon\MachineTranslatorAddon;
use Tobento\App\Translation\Web\Test\FakeCollector;
use Tobento\App\User\UserRepositoryInterface;
use Tobento\Service\Notifier\Test\FakeNotifier;
use Tobento\Service\Notifier\ChannelMessagesInterface;
use Tobento\Service\Notifier\Notification;
use Tobento\Service\Queue\Job;

class CollectTranslationsJobHandlerTest extends \Tobento\App\Testing\TestCase
{
    use RefreshDatabases;
    use MachineTranslatorAddon;

    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }

    public function testDoesNothingIfCollectorIdsMissing()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        $handler = new CollectTranslationsJobHandler(
            app: $app,
            collectors: $app->get(\Tobento\App\Translation\Web\Collector\CollectorsInterface::class),
            userRepository: $app->get(UserRepositoryInterface::class),
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(name: 'collect', payload: []);

        $handler->handleJob($job);

        $fakeNotifier->assertNothingSent();
    }

    public function testDoesNothingIfCollectorIdsEmpty()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        $handler = new CollectTranslationsJobHandler(
            app: $app,
            collectors: $app->get(\Tobento\App\Translation\Web\Collector\CollectorsInterface::class),
            userRepository: $app->get(UserRepositoryInterface::class),
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(name: 'collect', payload: ['collector_ids' => []]);

        $handler->handleJob($job);

        $fakeNotifier->assertNothingSent();
    }

    public function testCollectorRunsAndUserNotified()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        $collectors = new Collectors(
            new FakeCollector(
                id: 'fake',
                name: 'Fake Collector',
                created: 2,
                updated: 1,
                skipped: 3,
            )
        );

        // Create user
        $users = $app->get(UserRepositoryInterface::class);
        $user = $users->create([
            'email' => 'test@example.com',
            'name'  => 'Test User',
        ]);

        $handler = new CollectTranslationsJobHandler(
            app: $app,
            collectors: $collectors,
            userRepository: $users,
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(
            name: 'collect',
            payload: [
                'collector_ids' => ['fake'],
                'user_id' => $user->id(),
            ],
        );

        $handler->handleJob($job);

        $fakeNotifier->assertSent(Notification::class, function(ChannelMessagesInterface $messages) use ($user) {
            $notification = $messages->notification();
            $recipient = $messages->recipient();

            return $recipient->user()->id() === $user->id()
                && str_contains($notification->getSubject(), 'Translation collection completed')
                && str_contains($notification->getContent(), 'Created: 2')
                && str_contains($notification->getContent(), 'Updated: 1')
                && str_contains($notification->getContent(), 'Skipped: 3');
        });
    }

    public function testUserNotFoundMeansNoNotification()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        $collectors = new Collectors(
            new FakeCollector(
                id: 'fake',
                name: 'Fake Collector',
                created: 1,
                updated: 0,
                skipped: 0,
            )
        );
        
        $handler = new CollectTranslationsJobHandler(
            app: $app,
            collectors: $collectors,
            userRepository: $app->get(UserRepositoryInterface::class),
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(
            name: 'collect',
            payload: [
                'collector_ids' => ['fake'],
                'user_id' => 9999, // nonexistent
            ],
        );

        $handler->handleJob($job);

        $fakeNotifier->assertNothingSent();
    }

    public function testMultipleCollectorsAreHandled()
    {
        $app = $this->bootingApp();
        $fakeNotifier = $this->fakeNotifier();

        $collectors = new Collectors(
            new FakeCollector(
                id: 'c1',
                name: 'Collector One',
                created: 1,
                updated: 2,
                skipped: 0,
            ),
            new FakeCollector(
                id: 'c2',
                name: 'Collector Two',
                created: 0,
                updated: 1,
                skipped: 4,
            ),
        );

        $users = $app->get(UserRepositoryInterface::class);
        $user = $users->create(['email' => 'test@example.com', 'name' => 'Test User']);

        $handler = new CollectTranslationsJobHandler(
            app: $app,
            collectors: $collectors,
            userRepository: $users,
            notifier: $app->get(\Tobento\Service\Notifier\NotifierInterface::class),
        );

        $job = new Job(
            name: 'collect',
            payload: [
                'collector_ids' => ['c1', 'c2'],
                'user_id' => $user->id(),
            ],
        );

        $handler->handleJob($job);

        $fakeNotifier->assertSent(Notification::class, function(ChannelMessagesInterface $messages) {
            $content = $messages->notification()->getContent();

            return str_contains($content, 'Collector One')
                && str_contains($content, 'Created: 1')
                && str_contains($content, 'Updated: 2')
                && str_contains($content, 'Skipped: 0')
                && str_contains($content, 'Collector Two')
                && str_contains($content, 'Created: 0')
                && str_contains($content, 'Updated: 1')
                && str_contains($content, 'Skipped: 4');
        });
    }
}