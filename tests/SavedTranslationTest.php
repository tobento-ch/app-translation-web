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

use PHPUnit\Framework\TestCase;
use Tobento\App\Translation\Web\SavedTranslation;
use Tobento\App\Translation\Web\SavedTranslationInterface;
use Tobento\App\Translation\Web\SavedTranslationStatus;
use Tobento\App\Translation\Web\TranslationEntity;
use Tobento\App\Translation\Web\TranslationEntityInterface;

class SavedTranslationTest extends TestCase
{
    public function testImplementsInterface()
    {
        $entity = new TranslationEntity(['id' => 1]);

        $saved = new SavedTranslation(
            status: SavedTranslationStatus::CREATED,
            entity: $entity,
            skippedReason: null
        );

        $this->assertInstanceOf(
            SavedTranslationInterface::class,
            $saved
        );
    }

    public function testStoresStatusEntityAndReason()
    {
        $entity = new TranslationEntity([
            'id' => 1,
            'message' => 'hello',
            'translation' => 'Hello World',
        ]);

        $saved = new SavedTranslation(
            status: SavedTranslationStatus::UPDATED,
            entity: $entity,
            skippedReason: 'duplicate'
        );

        $this->assertSame(SavedTranslationStatus::UPDATED, $saved->status());
        $this->assertSame($entity, $saved->entity());
        $this->assertSame('duplicate', $saved->skippedReason());
    }

    public function testSkippedReasonCanBeNull()
    {
        $entity = new TranslationEntity([
            'id' => 2,
            'message' => 'bye',
            'translation' => 'Goodbye',
        ]);

        $saved = new SavedTranslation(
            status: SavedTranslationStatus::CREATED,
            entity: $entity,
            skippedReason: null
        );

        $this->assertSame(SavedTranslationStatus::CREATED, $saved->status());
        $this->assertSame($entity, $saved->entity());
        $this->assertNull($saved->skippedReason());
    }
}