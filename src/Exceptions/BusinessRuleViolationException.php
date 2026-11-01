<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Exceptions;

/**
 * BusinessRuleViolationException
 *
 * Categorized business-rule failure (category BusinessRuleViolation → result
 * status business_rule_failure). It may be thrown from any pipeline phase,
 * including business execution, and is always classified as a business-rule
 * failure. Extends \RuntimeException, the documented business-rule failure
 * exception of BusinessFunctionAbstract::execute().
 *
 * @package WaysNX\BusinessFramework\Exceptions
 */
class BusinessRuleViolationException extends \RuntimeException implements CategorizedExceptionInterface
{
    public function getCategory(): string
    {
        return self::CATEGORY_BUSINESS_RULE;
    }
}
