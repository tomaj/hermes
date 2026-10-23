<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\HermesException;

/**
 * FileSystemDriver could not create its directories or write, move or read a message file.
 */
final class FileSystemDriverException extends HermesException {}
