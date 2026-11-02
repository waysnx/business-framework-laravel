<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Exceptions;

/**
 * AuthorizationException
 *
 * Thrown by the Business Function pipeline when authorization fails closed:
 * no authorizer is configured, the authorizer returned an invalid decision,
 * or the authorizer explicitly denied execution.
 *
 * Extends \RuntimeException so existing callers that catch \RuntimeException
 * for authorization failures (as documented on BusinessFunctionAbstract::execute)
 * continue to work.
 *
 * WBF error category: AuthorizationError (WBF-SPEC-0005 §21).
 *
 * @package WaysNX\BusinessFramework\Exceptions
 */
class AuthorizationException extends \RuntimeException implements CategorizedExceptionInterface
{
    public const CATEGORY = 'AuthorizationError';

    public const REASON_NO_AUTHORIZER = 'no_authorizer';
    public const REASON_INVALID_DECISION = 'invalid_decision';
    public const REASON_DENIED = 'denied';

    private string $reasonCode;

    public function __construct(string $message, string $reasonCode = self::REASON_DENIED, ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
        $this->reasonCode = $reasonCode;
    }

    /**
     * Machine-readable reason: no_authorizer | invalid_decision | denied
     */
    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }

    public function getCategory(): string
    {
        return self::CATEGORY;
    }
}
