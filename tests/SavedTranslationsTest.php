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
use Tobento\App\Translation\Web\SavedTranslations;
use Tobento\App\Translation\Web\SavedTranslationsInterface;
use Tobento\App\Translation\Web\SavedTranslation;
use Tobento\App\Translation\Web\SavedTranslationStatus;
use Tobento\App\Translation\Web\TranslationEntity;

class SavedTranslationsTest extends TestCase
{
    public function testImplementsInterface()
    {
        $collection = new SavedTranslations();

        $this->assertInstanceOf(
            SavedTranslationsInterface::class,
            $collection
        );
    }

    public function testAddAndAll()
    {
        $t1 = new SavedTranslation(
            SavedTranslationStatus::CREATED,
            new TranslationEntity(['id' => 1])
        );

        $t2 = new SavedTranslation(
            SavedTranslationStatus::UPDATED,
            new TranslationEntity(['id' => 2])
        );

        $collection = new SavedTranslations();
        $collection->add($t1)->add($t2);

        $this->assertSame([$t1, $t2], $collection->all());
    }

    public function testFilter()
    {
        $t1 = new SavedTranslation(
            SavedTranslationStatus::CREATED,
            new TranslationEntity(['id' => 1])
        );

        $t2 = new SavedTranslation(
            SavedTranslationStatus::UPDATED,
            new TranslationEntity(['id' => 2])
        );

        $collection = new SavedTranslations();
        $collection->add($t1)->add($t2);

        $filtered = $collection->filter(
            fn($t) => $t->status() === SavedTranslationStatus::CREATED
        );

        $this->assertSame([$t1], $filtered->all());
        $this->assertNotSame($collection, $filtered); // immutability
    }

    public function testCreated()
    {
        $created = new SavedTranslation(
            SavedTranslationStatus::CREATED,
            new TranslationEntity(['id' => 1])
        );

        $updated = new SavedTranslation(
            SavedTranslationStatus::UPDATED,
            new TranslationEntity(['id' => 2])
        );

        $collection = new SavedTranslations();
        $collection->add($created)->add($updated);

        $result = $collection->created();

        $this->assertSame([$created], $result->all());
        $this->assertNotSame($collection, $result); // immutability
        $this->assertSame([$created, $updated], $collection->all()); // original unchanged
    }

    public function testUpdated()
    {
        $created = new SavedTranslation(
            SavedTranslationStatus::CREATED,
            new TranslationEntity(['id' => 1])
        );

        $updated = new SavedTranslation(
            SavedTranslationStatus::UPDATED,
            new TranslationEntity(['id' => 2])
        );

        $collection = new SavedTranslations();
        $collection->add($created)->add($updated);

        $result = $collection->updated();

        $this->assertSame([$updated], $result->all());
        $this->assertNotSame($collection, $result); // immutability
        $this->assertSame([$created, $updated], $collection->all()); // original unchanged
    }

    public function testSkipped()
    {
        $skipped = new SavedTranslation(
            SavedTranslationStatus::SKIPPED,
            new TranslationEntity(['id' => 1]),
            skippedReason: 'unchanged'
        );

        $created = new SavedTranslation(
            SavedTranslationStatus::CREATED,
            new TranslationEntity(['id' => 2])
        );

        $collection = new SavedTranslations();
        $collection->add($skipped)->add($created);

        $result = $collection->skipped();

        $this->assertSame([$skipped], $result->all());
        $this->assertNotSame($collection, $result); // immutability
        $this->assertSame([$skipped, $created], $collection->all()); // original unchanged
    }

    public function testIterator()
    {
        $t1 = new SavedTranslation(
            SavedTranslationStatus::CREATED,
            new TranslationEntity(['id' => 1])
        );

        $t2 = new SavedTranslation(
            SavedTranslationStatus::UPDATED,
            new TranslationEntity(['id' => 2])
        );

        $collection = new SavedTranslations();
        $collection->add($t1)->add($t2);

        $items = [];
        foreach ($collection as $item) {
            $items[] = $item;
        }

        $this->assertSame([$t1, $t2], $items);
    }

    public function testCount()
    {
        $collection = new SavedTranslations();

        $collection->add(new SavedTranslation(
            SavedTranslationStatus::CREATED,
            new TranslationEntity(['id' => 1])
        ));

        $collection->add(new SavedTranslation(
            SavedTranslationStatus::UPDATED,
            new TranslationEntity(['id' => 2])
        ));

        $this->assertSame(2, $collection->count());
    }
}