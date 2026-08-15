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

use Tobento\App\Translation\Web\Console\GenerateJsonTranslationFilesCommand;
use Tobento\Service\Console\ConsoleInterface;
use Tobento\Service\Queue\JobHandlerInterface;
use Tobento\Service\Queue\JobInterface;

/**
 * Triggers JSON translation file generation for a specific app.
 */
class GenerateJsonTranslationFilesJobHandler implements JobHandlerInterface
{
    public function __construct(
        protected ConsoleInterface $console,
    ) {}

    public function handleJob(JobInterface $job): void
    {
        $appId = $job->getPayload()['app_id'] ?? null;

        if ($appId === null) {
            return;
        }
        
        $this->console->execute(
            command: GenerateJsonTranslationFilesCommand::class,
            input: [
                '--appId' => [$appId],
            ],
        );
    }
}