<?php

declare(strict_types=1);

namespace WavyKit\Exceptions;

use Throwable;

/** Marker for every exception WavyKit throws; catch it to handle all WavyKit errors. */
interface WavyKitException extends Throwable {}
