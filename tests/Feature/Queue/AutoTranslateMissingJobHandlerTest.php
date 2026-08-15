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

use InvalidArgumentException;
use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Queue\AutoTranslateMissingJobHandler;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Service\MachineTranslator\MachineTranslatorsInterface;
use Tobento\Service\Queue\Job;
use Tobento\Service\Queue\JobInterface;

class AutoTranslateMissingJobHandlerTest extends \Tobento\App\Testing\TestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    use \Tobento\App\Translation\Web\Test\Addon\MachineTranslatorAddon;

    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }
    
    public function testThrowsIfTargetLocaleMissing()
    {
        $this->withMachineTranslator();

        $app = $this->bootingApp();

        $handler = new AutoTranslateMissingJobHandler(
            machineTranslators: $app->get(MachineTranslatorsInterface::class),
            translationRepository: $app->get(TranslationRepositoryInterface::class),
        );

        $job = new Job(
            name: 'missing',
            payload: [
                'text' => 'hello',
                // no targetLocale
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('targetLocale');

        $handler->handleJob($job);
    }

    public function testThrowsIfNoTextOrTextsProvided()
    {
        $this->withMachineTranslator();

        $app = $this->bootingApp();

        $handler = new AutoTranslateMissingJobHandler(
            machineTranslators: $app->get(MachineTranslatorsInterface::class),
            translationRepository: $app->get(TranslationRepositoryInterface::class),
        );

        $job = new Job(
            name: 'missing',
            payload: [
                'targetLocale' => 'fr',
                // no text, no texts
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires "text" or "texts"');

        $handler->handleJob($job);
    }

    public function testSingleTextIsTranslatedAndSaved()
    {
        $this->withMachineTranslator();

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $handler = new AutoTranslateMissingJobHandler(
            machineTranslators: $app->get(MachineTranslatorsInterface::class),
            translationRepository: $repo,
        );

        $job = new Job(
            name: 'missing',
            payload: [
                'text' => 'hello',
                'targetLocale' => 'fr',
                'appId' => 'root',
            ],
        );

        $handler->handleJob($job);

        $saved = $repo->findAll(where: ['resource_locale' => 'fr'])->first();

        $this->assertSame('hello', $saved->message());
        $this->assertSame('Test: hello', $saved->translation());
        $this->assertSame('translations', $saved->translatedBy());
    }

    public function testMultipleTextsAreTranslatedAndSaved()
    {
        $this->withMachineTranslator();

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $handler = new AutoTranslateMissingJobHandler(
            machineTranslators: $app->get(MachineTranslatorsInterface::class),
            translationRepository: $repo,
        );

        $job = new Job(
            name: 'missing',
            payload: [
                'texts' => ['a', 'b'],
                'targetLocale' => 'fr',
                'appId' => 'root',
            ],
        );

        $handler->handleJob($job);

        $saved = $repo->findAll(where: ['resource_locale' => 'fr']);

        $translations = $saved->map(fn($e) => $e->translation());

        $this->assertContains('Test: a', $translations);
        $this->assertContains('Test: b', $translations);
    }

    public function testTranslatorNameIsUsedIfValid()
    {
        $this->withMachineTranslator();

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);
        $translators = $app->get(MachineTranslatorsInterface::class);

        $translatorName = $translators->names()[0];

        $handler = new AutoTranslateMissingJobHandler(
            machineTranslators: $translators,
            translationRepository: $repo,
        );

        $job = new Job(
            name: 'missing',
            payload: [
                'text' => 'hello',
                'targetLocale' => 'fr',
                'translator' => $translatorName,
            ],
        );

        $handler->handleJob($job);

        $saved = $repo->findAll(where: ['resource_locale' => 'fr'])->first();

        $this->assertSame('Test: hello', $saved->translation());
        $this->assertSame($translatorName, $saved->translatedBy());
    }

    public function testFallbackTranslatorIsUsedIfNameInvalid()
    {
        $this->withMachineTranslator();

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);
        $translators = $app->get(MachineTranslatorsInterface::class);

        $fallback = $translators->names()[0];

        $handler = new AutoTranslateMissingJobHandler(
            machineTranslators: $translators,
            translationRepository: $repo,
        );

        $job = new Job(
            name: 'missing',
            payload: [
                'text' => 'hello',
                'targetLocale' => 'fr',
                'translator' => 'nonexistent-translator',
            ],
        );

        $handler->handleJob($job);

        $saved = $repo->findAll(where: ['resource_locale' => 'fr'])->first();

        $this->assertSame('Test: hello', $saved->translation());
        $this->assertSame($fallback, $saved->translatedBy());
    }
}