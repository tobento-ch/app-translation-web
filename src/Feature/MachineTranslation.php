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

namespace Tobento\App\Translation\Web\Feature;

use Tobento\App\Boot;

class MachineTranslation extends Boot
{
    public const INFO = [
        'boot' => [
            'Boots machine translator',
        ],
    ];
    
    public const BOOT = [
        \Tobento\App\MachineTranslator\Boot\MachineTranslator::class,
    ];
    
    /**
     * Create a new instance.
     */
    public function __construct()
    {
        //
    }
    
    /**
     * Boot application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}