<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Runtime;

/**
 * ExecutionStatus
 *
 * The WBF Business Function result statuses (WBF-SPEC-0006, ADR-0009,
 * decision D1 approved 2026-11-01). Identical to the Node runtime's
 * BusinessFunctionStatus values.
 *
 * @package WaysNX\BusinessFramework\Runtime
 */
final class ExecutionStatus
{
    public const SUCCESS = 'success';
    public const VALIDATION_FAILURE = 'validation_failure';
    public const AUTHORIZATION_FAILURE = 'authorization_failure';
    public const NOT_FOUND = 'not_found';
    public const BUSINESS_RULE_FAILURE = 'business_rule_failure';
    public const EXECUTION_FAILURE = 'execution_failure';

    public const ALL = [
        self::SUCCESS,
        self::VALIDATION_FAILURE,
        self::AUTHORIZATION_FAILURE,
        self::NOT_FOUND,
        self::BUSINESS_RULE_FAILURE,
        self::EXECUTION_FAILURE,
    ];

    private function __construct()
    {
    }
}
