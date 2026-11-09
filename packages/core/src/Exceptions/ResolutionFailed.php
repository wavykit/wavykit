<?php

declare(strict_types=1);

namespace WavyKit\Exceptions;

use LogicException;

/**
 * Output could not be resolved for the current request.
 *
 * Raised for failing or invalid label, parameter, and visibility callbacks,
 * missing route parameters, URL generation failures, and unknown menu or page
 * lookups. The underlying exception, when there is one, is the previous one.
 */
final class ResolutionFailed extends LogicException implements WavyKitException {}
