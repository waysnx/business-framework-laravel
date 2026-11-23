<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Exceptions;

/**
 * CategorizedExceptionInterface
 *
 * Implemented by exceptions that carry a WBF error category
 * (WBF-SPEC-0005 §21, WBF-SPEC-0006). BusinessFunctionRuntime::run() uses the
 * category to classify a failure before any pipeline-phase fallback.
 *
 * Categories with a dedicated result status:
 * - ValidationError       → validation_failure
 * - AuthorizationError    → authorization_failure
 * - ResourceNotFound      → not_found
 * - BusinessRuleViolation → business_rule_failure
 * Any other category      → execution_failure
 *
 * @package WaysNX\BusinessFramework\Exceptions
 */
interface CategorizedExceptionInterface
{
    public const CATEGORY_VALIDATION = 'ValidationError';
    public const CATEGORY_AUTHORIZATION = 'AuthorizationError';
    public const CATEGORY_RESOURCE_NOT_FOUND = 'ResourceNotFound';
    public const CATEGORY_BUSINESS_RULE = 'BusinessRuleViolation';

    /**
     * WBF error category of this exception.
     */
    public function getCategory(): string;
}
