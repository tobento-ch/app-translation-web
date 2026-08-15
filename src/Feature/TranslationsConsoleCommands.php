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

use Tobento\App\AppInterface;
use Tobento\App\Boot;
use Tobento\App\Translation\Web\Collector\CollectorInterface;
use Tobento\Service\Console\ConsoleInterface;

class TranslationsConsoleCommands extends Boot
{
    public const INFO = [
        'boot' => [
            'Registers translations console commands',
        ],
    ];
    
    public const BOOT = [
        \Tobento\App\Console\Boot\Console::class,
        \Tobento\App\Translation\Boot\Translation::class,
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
     * @param AppInterface $app
     * @return void
     */
    public function boot(AppInterface $app): void
    {
        // Console commands:
        $app->on(ConsoleInterface::class, static function(ConsoleInterface $console): void {
            $console->addCommand(\Tobento\App\Translation\Web\Console\CollectTranslationsCommand::class);
            $console->addCommand(\Tobento\App\Translation\Web\Console\GenerateJsonTranslationFilesCommand::class);
        });
    }
}