<?php

declare(strict_types=1);

namespace WavyKit\Exceptions;

use LogicException;

/**
 * A page or menu declaration is invalid, or was registered too late.
 *
 * Raised while registering or finalizing declarations, or when a declaration
 * references something that does not exist, such as an unknown route.
 */
final class InvalidDefinition extends LogicException implements WavyKitException {}
