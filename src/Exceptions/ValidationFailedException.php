<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Exceptions;

/**
 * ValidationFailedException
 *
 * Categorized request-validation failure (category ValidationError →
 * result status validation_failure). Extends \InvalidArgumentException, the
 * documented validation-failure exception of BusinessFunctionAbstract::execute(),
 * so existing catch blocks keep working.
 *
 * @package WaysNX\BusinessFramework\Exceptions
 */
class ValidationFailedException extends \InvalidArgumentException implements CategorizedExceptionInterface
{
    public function getCategory(): string
    {
        return self::CATEGORY_VALIDATION;
    }
}
