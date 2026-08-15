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

use Tobento\App\Translation\Web\Collector\CollectedTranslationsInterface;
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\App\Translation\Web\Onboarding\AutoTranslatedTranslationsInterface;
use Tobento\App\Translation\Web\Onboarding\CreatedTranslationsInterface;
use Tobento\App\Translation\Web\Onboarding\OnboardingInterface;
use Tobento\App\Translation\Web\Onboarding\PublishedTranslationsInterface;
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
 * Job handler for automatically translating translation entries.
 *
 * Processes queued auto-translation jobs by translating missing or
 * outdated values for the specified locale and application. The handler
 * delegates translation work to the configured machine translator and
 * updates the affected translation entries accordingly.
 *
 * This job is executed asynchronously to avoid blocking user actions
 * and to support large translation batches.
 */
class AutoTranslateJobHandler implements JobHandlerInterface
{
    public function __construct(
        protected OnboardingInterface $onboarding,
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
        $payload = $job->getPayload();
        $where = $payload['filters']['where'] ?? [];
        $orderBy = $payload['filters']['orderBy'] ?? [];
        
        // Auto-translate
        $autoTranslated = $this->onboarding->autoTranslateEntriesBy(
            where: $where,
            orderBy: $orderBy,
            limit: [],
        );
        
        // Notify user if available
        $userId = $payload['user_id'] ?? null;
        
        if (is_string($userId) || is_int($userId)) {
            $this->notifyUser(
                userId: $userId,
                autoTranslated: $autoTranslated,
            );
        }
    }

    /**
     * Notifies the user about onboarding results.
     *
     * @param int|string $userId
     * @param AutoTranslatedTranslationsInterface $autoTranslated
     * @return void
     */
    protected function notifyUser(
        int|string $userId,
        AutoTranslatedTranslationsInterface $autoTranslated,
    ): void {
        if (is_null($recipient = $this->createRecipient(userId: $userId))) {
            return;
        }

        $notification = $this->createNotification(
            autoTranslated: $autoTranslated,
        );
        
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
     * Creates the onboarding notification.
     *
     * @param AutoTranslatedTranslationsInterface $autoTranslated
     * @return Notification
     */
    protected function createNotification(
        AutoTranslatedTranslationsInterface $autoTranslated,
    ): Notification {

        $title = trans('Auto‑translation completed');

        $translated = $autoTranslated->saved()->created()->count() + $autoTranslated->saved()->updated()->count();
        $skipped = $autoTranslated->saved()->skipped()->count();

        $lines = [
            trans('Auto-translated records') =>
                trans('Translated: :count', [':count' => $translated]) . ' ' .
                trans('Skipped: :count', [':count' => $skipped]),
        ];

        $contentLines = [];
        foreach ($lines as $section => $summary) {
            $contentLines[] = $section . ': ' . $summary;
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