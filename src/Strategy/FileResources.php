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

use InvalidArgumentException;
use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Queue\GenerateJsonTranslationFilesJobHandler;
use Tobento\Service\Dir\DirsInterface;
use Tobento\Service\Queue\Job;
use Tobento\Service\Queue\QueueInterface;

/**
 * A translation publish strategy that registers a custom translation
 * directory and delegates file generation to a scheduled publish.
 *
 * The publish() method registers the directory, while schedulePublish()
 * is intended to generate translation files via a queue job.
 */
final class FileResources implements TranslationsPublishInterface
{
    /**
     * Create a new instance.
     *
     * @param string|null $queueName Queue name for dispatching the file-generation job,
     *    or null to use the default queue.
     * @param string $folderName Folder name under the app directory.
     * @param string $dirName Directory identifier used by the Dirs service.
     * @param int $dirPriority Directory priority (higher overrides defaults).
     */
    public function __construct(
        private null|string $queueName = null,
        private string $folderName = 'trans-custom',
        private string $dirName = 'trans-custom',
        private int $dirPriority = 10000,
    ) {
        if (!preg_match('/^[a-z\-]+$/', $folderName)) {
            throw new InvalidArgumentException(
                sprintf('The folderName "%s" contains invalid characters only a-z characters and dashes.', $folderName)
            );
        }
        
        if ($folderName === 'trans') {
            throw new InvalidArgumentException('The folderName "trans" is not allowed');
        }
        
        if (!preg_match('/^[a-z\-]+$/', $dirName)) {
            throw new InvalidArgumentException(
                sprintf('The dirName "%s" contains invalid characters only a-z characters and dashes.', $dirName)
            );
        }
        
        if ($dirName === 'trans') {
            throw new InvalidArgumentException('The dirName "trans" is not allowed');
        }
    }

    /**
     * Returns the folder name.
     *
     * @return string
     */
    public function folderName(): string
    {
        return $this->folderName;
    }
    
    /**
     * Returns the dir name.
     *
     * @return string
     */
    public function dirName(): string
    {
        return $this->dirName;
    }
    
    /**
     * Registers the custom translation directory.
     *
     * @param AppInterface $app The application instance.
     * @return void
     */
    public function publish(AppInterface $app): void
    {
        if ($app->dirs()->has($this->dirName)) {
            throw new InvalidArgumentException(
                sprintf('The directory "%s" already exists', $this->dirName)
            );
        }

        $app->dirs()->dir(
            dir: $app->dir('app').$this->folderName.'/',
            
            // do not use 'trans' as name for migration purposes
            name: $this->dirName,

            group: 'trans',

            // add higher priority as default trans dir:
            priority: $this->dirPriority,
        );
    }

    /**
     * Dispatches a job to generate translation files asynchronously.
     *
     * This method validates that the target directory does not already exist,
     * then pushes a unique job onto the configured queue. The job handler is
     * responsible for creating the directory and writing all published
     * translation files.
     *
     * @param AppInterface $app
     * @return void
     */
    public function schedulePublish(AppInterface $app): void
    {
        $queue = $app->get(QueueInterface::class);
        
        $job = new Job(
            name: GenerateJsonTranslationFilesJobHandler::class,
            payload: ['app_id' => $app->id()],
        );
        
        if ($this->queueName) {
            $job->queue($this->queueName);
        }
        
        // Prevent multiple identical jobs from being queued,
        // avoiding overlapping file writes and redundant processing
        $job->unique();
        
        $job->retry(3);
        $job->priority(-50);

        $queue->push($job);
    }
}