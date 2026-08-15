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

namespace Tobento\App\Translation\Web\MissingHandler;

use Psr\Log\LoggerInterface;
use Tobento\App\Logging\LoggerTrait;
use Tobento\Service\Translation\MissingTranslationHandler;

class Log extends MissingTranslationHandler
{
    use LoggerTrait;

    /**
     * Logs a message if a logger has been provided.
     *
     * This method is a lightweight wrapper around the PSR-3 logger,
     * allowing the ImageProcessor to perform optional logging without
     * requiring a logger implementation. If no logger is set, the call
     * is silently ignored.
     *
     * @param string $level The PSR-3 log level (use LogLevel::* constants).
     * @param string $message The log message.
     * @param array  $context Additional context passed to the logger.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $this->getLogger()->log($level, $message, $context);
    }
}