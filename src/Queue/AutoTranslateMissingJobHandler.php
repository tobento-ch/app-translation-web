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

use InvalidArgumentException;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Service\MachineTranslator\MachineTranslatorsInterface;
use Tobento\Service\Queue\JobHandlerInterface;
use Tobento\Service\Queue\JobInterface;

/**
 * Handles queued auto‑translation jobs.
 */
class AutoTranslateMissingJobHandler implements JobHandlerInterface
{
    public function __construct(
        protected MachineTranslatorsInterface $machineTranslators,
        protected TranslationRepositoryInterface $translationRepository,
    ) {}

    public function handleJob(JobInterface $job): void
    {
        $payload = $job->getPayload();

        $text = $payload['text'] ?? null;
        $texts = $payload['texts'] ?? null;
        $targetLocale = $payload['targetLocale'] ?? null;
        $translatorName = $payload['translator'] ?? null;

        if (!$targetLocale) {
            throw new InvalidArgumentException('Auto-translate job missing required "targetLocale".');
        }
        
        if (!$text && empty($texts)) {
            throw new InvalidArgumentException('Auto-translate job requires "text" or "texts".');
        }

        // Resolve translator
        if ($translatorName && $this->machineTranslators->has($translatorName)) {
            $translator = $this->machineTranslators->get($translatorName);
        } else {
            $fallback = $this->machineTranslators->names()[0] ?? 'null';
            $translator = $this->machineTranslators->get($fallback);
        }

        // SINGLE TEXT
        if (is_string($text) && $text !== '') {

            $translated = $translator->translate(
                text: $text,
                locale: $targetLocale,
            );

            $this->translationRepository->saveTranslation(
                appId: $payload['appId'] ?? 'root',
                resourceName: $payload['resourceName'] ?? '*',
                resourceLocale: $targetLocale,
                resourceGroup: $payload['resourceGroup'] ?? 'trans',
                resourceFilename: $payload['resourceFilename'] ?? null,
                resourcePriority: $payload['resourcePriority'] ?? 0,
                message: $text,
                translation: $translated,
                translatedBy: $translator->name(),
            );

            return;
        }
        
        // MULTIPLE TEXTS
        if (is_array($texts) && !empty($texts)) {

            $translatedList = $translator->translateMany(
                texts: $texts,
                locale: $targetLocale,
            );

            foreach ($translatedList as $i => $translated) {
                $original = $texts[$i] ?? null;
                if (!$original) {
                    continue;
                }

                $this->translationRepository->saveTranslation(
                    appId: $payload['appId'] ?? 'root',
                    resourceName: $payload['resourceName'] ?? '*',
                    resourceLocale: $targetLocale,
                    resourceGroup: $payload['resourceGroup'] ?? 'trans',
                    resourceFilename: $payload['resourceFilename'] ?? null,
                    resourcePriority: $payload['resourcePriority'] ?? 0,
                    message: $original,
                    translation: $translated,
                    translatedBy: $translator->name(),
                );
            }
        }
    }
}