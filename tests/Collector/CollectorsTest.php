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

namespace Tobento\App\Translation\Web\Test\Collector;

use PHPUnit\Framework\TestCase;
use Tobento\App\Translation\Web\Collector\AllTranslations;
use Tobento\App\Translation\Web\Collector\Collectors;
use Tobento\App\Translation\Web\Collector\CollectorsInterface;

class CollectorsTest extends TestCase
{
    protected function make(string $id): AllTranslations
    {
        return new AllTranslations(
            id: $id,
            name: 'Test',
            appId: 'root',
        );
    }

    public function testImplementsInterface()
    {
        $this->assertInstanceof(CollectorsInterface::class, new Collectors());
    }
    
    public function testHasAndGet()
    {
        $c1 = $this->make('one');
        $c2 = $this->make('two');

        $collectors = new Collectors($c1, $c2);

        $this->assertTrue($collectors->has('one'));
        $this->assertTrue($collectors->has('two'));
        $this->assertFalse($collectors->has('three'));

        $this->assertSame($c1, $collectors->get('one'));
        $this->assertSame($c2, $collectors->get('two'));
        $this->assertNull($collectors->get('three'));
    }

    public function testFirst()
    {
        $c1 = $this->make('one');
        $c2 = $this->make('two');

        $collectors = new Collectors($c1, $c2);

        $this->assertSame($c1, $collectors->first());
    }

    public function testAllAndIds()
    {
        $c1 = $this->make('one');
        $c2 = $this->make('two');

        $collectors = new Collectors($c1, $c2);

        $this->assertSame(
            ['one' => $c1, 'two' => $c2],
            $collectors->all()
        );

        $this->assertSame(['one', 'two'], $collectors->ids());
    }

    public function testFilter()
    {
        $c1 = $this->make('one');
        $c2 = $this->make('two');

        $collectors = new Collectors($c1, $c2);

        $filtered = $collectors->filter(fn($c) => $c->id() === 'two');
        
        $this->assertNotSame($collectors, $filtered);
        $this->assertSame(['two'], $filtered->ids());
        $this->assertSame($c2, $filtered->get('two'));
    }

    public function testOnly()
    {
        $c1 = $this->make('one');
        $c2 = $this->make('two');
        $c3 = $this->make('three');

        $collectors = new Collectors($c1, $c2, $c3);

        $only = $collectors->only('one', 'three');
        
        $this->assertNotSame($collectors, $only);
        $this->assertSame(['one', 'three'], $only->ids());
    }

    public function testExcept()
    {
        $c1 = $this->make('one');
        $c2 = $this->make('two');
        $c3 = $this->make('three');

        $collectors = new Collectors($c1, $c2, $c3);

        $except = $collectors->except('two');
        
        $this->assertNotSame($collectors, $except);
        $this->assertSame(['one', 'three'], $except->ids());
    }

    public function testIterator()
    {
        $c1 = $this->make('one');
        $c2 = $this->make('two');

        $collectors = new Collectors($c1, $c2);

        $iterated = [];

        foreach ($collectors as $id => $collector) {
            $iterated[$id] = $collector;
        }

        $this->assertSame(['one' => $c1, 'two' => $c2], $iterated);
    }

    public function testCount()
    {
        $c1 = $this->make('one');
        $c2 = $this->make('two');

        $collectors = new Collectors($c1, $c2);

        $this->assertSame(2, $collectors->count());
    }
}