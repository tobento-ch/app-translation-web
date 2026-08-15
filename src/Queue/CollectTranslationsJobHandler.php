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
use Tobento\App\Translation\Web\Collector\CollectedTranslationsInterface;
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\App\User\UserRepositoryInterface;
use Tobento\Service\Notifier\Message;
use Tobento\Service\Notifier\Notification;
use Tobento\Service\Notifier\NotifierInterface;
use Tobento\Service\Notifier\Parameter\Queue;
use Tobento\Service\Notifier\UserRecipient;
use Tobento\Service\Queue\JobHandlerInterface;
use Tobento\Service\Queue\JobInterface;
use function Tobento\App\Translation\trans;

/**
 * Collects translations using the payloads collectors IDs.
 */
class CollectTranslationsJobHandler implements JobHandlerInterface
{
    public function __construct(
        protected AppInterface $app,
        protected CollectorsInterface $collectors,
        protected UserRepositoryInterface $userRepository,
        protected NotifierInterface $notifier,
    ) {}

    /**
     * Handles job
     *
     * @param JobInterface $job
     * @return void
     */
    public function handleJob(JobInterface $job): void
    {
        $collectorIds = $job->getPayload()['collector_ids'] ?? null;

        if (!is_array($collectorIds) || empty($collectorIds)) {
            return;
        }
        
        $collectors = $this->collectors->only(...$collectorIds);
        
        // Collecting translations:
        $results = [];
        
        foreach($collectors as $collector) {
            $name = sprintf('%s (%s)', $collector->name(), $collector->id());
            $results[$name] = $collector->collect($this->app);
        }
        
        $userId = $job->getPayload()['user_id'] ?? null;
        
        if (is_string($userId) || is_int($userId)) {
            $this->notifyUser($userId, $results);
        }
    }

    /**
     * Notifies user
     *
     * @param int|string $userId
     * @param array<string, CollectedTranslationsInterface> $results
     * @return void
     */
    protected function notifyUser(int|string $userId, array $results): void
    {
        if (is_null($recipient = $this->createRecipient(userId: $userId))) {
            return;
        }

        $notification = $this->createNotification(results: $results);

        $this->notifier->send($notification, $recipient);
    }
    
    /**
     * Returns the created recipient or null if none.
     *
     * @param int|string $userId
     * @return null|UserRecipient
     */
    protected function createRecipient(int|string $userId): null|UserRecipient
    {
        $user = $this->userRepository->findById($userId);

        return $user ? new UserRecipient(user: $user) : null;
    }
    
    /**
     * Returns the create notification.
     *
     * @param array<string, CollectedTranslationsInterface> $results
     * @return Notification
     */
    protected function createNotification(array $results): Notification
    {
        $lines = [];

        foreach ($results as $collectorName => $collectedTranslations) {
            $saved = $collectedTranslations->saved();

            $created = $saved->created()->count();
            $updated = $saved->updated()->count();
            $skipped = $saved->skipped()->count();

            $lines[$collectorName] =
                trans('Created: :count', [':count' => $created]) . ' ' .
                trans('Updated: :count', [':count' => $updated]) . ' ' .
                trans('Skipped: :count', [':count' => $skipped]);
        }

        $title = trans('Translation collection completed');

        $contentLines = [];

        foreach ($lines as $collectorName => $summary) {
            $contentLines[] = $collectorName . ': ' . $summary;
        }

        $notification = new Notification(
            subject: $title,
            content: nl2br(implode("\n", $contentLines)),
            channels: ['browser']
        );

        $notification->addMessage('browser', new Message\Browser([
            'title' => $title,
            'keyedList' => $lines,
            'status' => 'success',
            'autotimeout' => 10000,
        ]));

        return $notification;
    }
}