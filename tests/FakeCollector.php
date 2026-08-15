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

namespace Tobento\App\Translation\Web\Test;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Collector\CollectorInterface;
use Tobento\App\Translation\Web\Collector\CollectedTranslationsInterface;
use Tobento\App\Translation\Web\SavedTranslation;
use Tobento\App\Translation\Web\SavedTranslations;
use Tobento\App\Translation\Web\SavedTranslationStatus;
use Tobento\App\Translation\Web\TranslationEntity;

class FakeCollector implements CollectorInterface
{
    public function __construct(
        protected string $id,
        protected string $name,
        protected int $created = 0,
        protected int $updated = 0,
        protected int $skipped = 0,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function collect(AppInterface $app): CollectedTranslationsInterface
    {
        $saved = new SavedTranslations();

        for ($i = 0; $i < $this->created; $i++) {
            $saved->add(new SavedTranslation(
                status: SavedTranslationStatus::CREATED,
                entity: new TranslationEntity(),
            ));
        }

        for ($i = 0; $i < $this->updated; $i++) {
            $saved->add(new SavedTranslation(
                status: SavedTranslationStatus::UPDATED,
                entity: new TranslationEntity(),
            ));
        }

        for ($i = 0; $i < $this->skipped; $i++) {
            $saved->add(new SavedTranslation(
                status: SavedTranslationStatus::SKIPPED,
                entity: new TranslationEntity(),
            ));
        }

        return new class($saved) implements CollectedTranslationsInterface {
            public function __construct(private SavedTranslations $saved) {}
            public function saved(): SavedTranslations { return $this->saved; }
        };
    }
}