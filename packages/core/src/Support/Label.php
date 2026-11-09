<?php

declare(strict_types=1);

namespace WavyKit\Support;

use Closure;
use Throwable;
use WavyKit\Context;
use WavyKit\Exceptions\ResolutionFailed;

/** @internal Resolves string-or-callback labels; shared by every reader. */
final class Label
{
    /**
     * @param  string|Closure(Context): mixed  $value
     * @param  string  $owner  Names the page or menu entry in error messages.
     *
     * @throws ResolutionFailed If the callback throws or does not return a string.
     */
    public static function resolve(string|Closure $value, Context $context, string $owner): string
    {
        if (is_string($value)) {
            return $value;
        }
        try {
            $label = $value($context);
        } catch (Throwable $exception) {
            throw new ResolutionFailed("Could not resolve label for {$owner}: {$exception->getMessage()}", 0, $exception);
        }
        if (! is_string($label)) {
            throw new ResolutionFailed("Label callback for {$owner} must return a string.");
        }

        return $label;
    }
}
