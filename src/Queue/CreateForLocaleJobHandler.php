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
 * Handles onboarding for a specific locale by creating missing
 * translation entries for the given app without overwriting
 * existing or user-edited translations.
 *
 * This job is triggered by the CreateForLocaleBulkAction and
 * delegates the actual work to the LocaleOnboardingServiceInterface.
 */
class CreateForLocaleJobHandler implements JobHandlerInterface
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

        $locale = $payload['locale'] ?? null;
        $appId = $payload['app_id'] ?? null;
        $userId = $payload['user_id'] ?? null;
        $autoTranslate = $payload['auto_translate'] ?? false;
        $publish = $payload['publish'] ?? false;

        if (!is_string($locale) || !is_string($appId)) {
            return;
        }

        // Create missing entries
        $created = $this->onboarding->createTranslationEntries($locale, $appId);

        // Optionally auto-translate
        $autoTranslated = null;
        if ($autoTranslate === true) {
            $autoTranslated = $this->onboarding->autoTranslateEntries($locale, $appId);
        }
        
        // Optionally publish
        $published = null;
        if ($publish === true) {
            $published = $this->onboarding->publishTranslations($locale, $appId);
        }
        
        // Notify user if available
        if (is_string($userId) || is_int($userId)) {
            $this->notifyUser(
                userId: $userId,
                created: $created,
                autoTranslated: $autoTranslated,
                published: $published,
            );
        }
    }

    /**
     * Notifies the user about onboarding results.
     *
     * @param int|string $userId
     * @param CreatedTranslationsInterface $created
     * @param null|AutoTranslatedTranslationsInterface $autoTranslated
     * @param null|PublishedTranslationsInterface $published
     * @return void
     */
    protected function notifyUser(
        int|string $userId,
        CreatedTranslationsInterface $created,
        null|AutoTranslatedTranslationsInterface $autoTranslated,
        null|PublishedTranslationsInterface $published,
    ): void {
        if (is_null($recipient = $this->createRecipient(userId: $userId))) {
            return;
        }

        $notification = $this->createNotification(
            created: $created,
            autoTranslated: $autoTranslated,
            published: $published,
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
     * @param CreatedTranslationsInterface $created
     * @param null|AutoTranslatedTranslationsInterface $autoTranslated
     * @param null|PublishedTranslationsInterface $published
     * @return Notification
     */
    protected function createNotification(
        CreatedTranslationsInterface $created,
        null|AutoTranslatedTranslationsInterface $autoTranslated,
        null|PublishedTranslationsInterface $published,
    ): Notification {

        $title = trans('Locale onboarding completed');
        
        /** @var array<string, string> $lines */
        $lines = [];

        // Created entries
        $lines[trans('Created records')] =
            trans('Created: :count', [':count' => $created->saved()->created()->count()]) . ' ' . 
            trans('Skipped: :count', [':count' => $created->saved()->skipped()->count()]);

        // Auto-translated entries (optional)
        if ($autoTranslated !== null) {
            $translated = $autoTranslated->saved()->created()->count() + $autoTranslated->saved()->updated()->count();
            
            $lines[trans('Auto-translated records')] =
                trans('Translated: :count', [':count' => $translated]) . ' ' .
                trans('Skipped: :count', [':count' => $autoTranslated->saved()->skipped()->count()]);
        }

        // Published entries (optional)
        if ($published !== null) {
            $lines[trans('Published records')] =
                trans('Published: :count', [':count' => $published->saved()->updated()->count()]);
        }

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