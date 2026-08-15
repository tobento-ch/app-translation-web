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

namespace Tobento\App\Translation\Web\MissingHandler;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Queue\AutoTranslateMissingJobHandler;
use Tobento\Service\Queue\Job;
use Tobento\Service\Queue\QueueInterface;
use Tobento\Service\Translation\MissingTranslationHandlerInterface;

class AutoTranslate implements MissingTranslationHandlerInterface
{
    /**
     * Create a new instance.
     *
     * @param AppInterface $app
     * @param QueueInterface $queue
     * @param null|string $queueName
     * @param array<int, string> $auto
     * @param null|string $translatorName
     */    
    public function __construct(
        protected AppInterface $app,
        protected QueueInterface $queue,
        protected null|string $queueName = null,
        protected array $auto = ['missing'], // or ['missing', 'fallback', 'fallbackToDefault']
        protected null|string $translatorName = null,
    ) {}
    
    /**
     * Handle missing translation.
     *
     * @param string $translation The translated message.
     * @param string $message The message to translate.
     * @param array $parameters Any parameters for the message.
     * @param string $locale The locale such as de-CH.
     * @param string $requestedLocale The requested locale such as de-CH.
     * @return string The translated message.
     */
    public function missing(
        string $translation,
        string $message,
        array $parameters,
        string $locale,
        string $requestedLocale
    ): string {
        
        // Message might be a real message (not a keyword) and the default locale is used,
        // so it would not be a missing message.
        // Handle it here, depending on the message set on your views and such.

        if (in_array('missing', $this->auto, true)) {
            $this->pushJob(
                id: $this->app->id().$message.$requestedLocale,
                payload: [
                    'appId' => $this->app->id(),
                    'reason' => 'missing',
                    'translation' => $translation,
                    'text' => $message,
                    'parameters' => $parameters,
                    'locale' => $locale,
                    'targetLocale' => $requestedLocale,
                ],
            );
        }

        return $translation;
    }

    /**
     * Handle translation which uses its fallback locale.
     *
     * @param string $translation The translated message.
     * @param string $message The message to translate.
     * @param array $parameters Any parameters for the message.
     * @param string $fallbackLocale The fallback locale used for translation such as de-CH.
     * @param string $requestedLocale The requested locale such as de-CH.
     * @return string The translated message.
     */
    public function fallback(
        string $translation,
        string $message,
        array $parameters,
        string $fallbackLocale,
        string $requestedLocale
    ): string {
        if (in_array('fallback', $this->auto, true)) {
            $this->pushJob(
                id: $this->app->id().$message.$requestedLocale,
                payload: [
                    'appId' => $this->app->id(),
                    'reason' => 'fallback',
                    'translation' => $translation,
                    'text' => $message,
                    'parameters' => $parameters,
                    'fallbackLocale' => $fallbackLocale,
                    'targetLocale' => $requestedLocale,
                ],
            );
        }
        
        return $translation;
    }
    
    /**
     * Handle translation which fallbacked to default locale.
     *
     * @param string $translation The translated message.
     * @param string $message The message to translate.
     * @param array $parameters Any parameters for the message.
     * @param string $defaultLocale The default locale used for translation such as de-CH.
     * @param string $requestedLocale The requested locale such as de-CH.
     * @return string The translated message.
     */
    public function fallbackToDefault(
        string $translation,
        string $message,
        array $parameters,
        string $defaultLocale,
        string $requestedLocale
    ): string {

        if (in_array('fallbackToDefault', $this->auto, true)) {
            $this->pushJob(
                id: $this->app->id().$message.$requestedLocale,
                payload: [
                    'appId' => $this->app->id(),
                    'reason' => 'fallbackToDefault',
                    'translation' => $translation,
                    'text' => $message,
                    'parameters' => $parameters,
                    'defaultLocale' => $defaultLocale,
                    'targetLocale' => $requestedLocale,
                ],
            );
        }
            
        return $translation;
    }
    
    /**
     * Pushes an auto-translation job to the queue.
     *
     * @param string $id Unique identifier for deduplication.
     * @param array $payload Job data for the translation handler.
     * @return void
     */
    protected function pushJob(string $id, array $payload): void
    {
        $payload['translator'] = $this->translatorName;
        
        $job = new Job(
            name: AutoTranslateMissingJobHandler::class,
            payload: $payload,
        );

        if ($this->queueName) {
            $job->queue($this->queueName);
        }
        
        // Prevent duplicate
        $job->unique(id: 'auto-translate:' . hash('sha256', $id));

        $job->retry(3);
                     
        // Lower priority so it doesn't block important jobs
        $job->priority(-50);

        $this->queue->push($job);
    }
}