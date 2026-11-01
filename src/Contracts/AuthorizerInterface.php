<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Contracts;

use WaysNX\BusinessFramework\Authorization\AuthorizationDecision;
use WaysNX\BusinessFramework\Types\BusinessFunctionInterface;

/**
 * AuthorizerInterface
 *
 * Host-application supplied authorization decision point for Business Functions.
 *
 * WBF does not authenticate users or own policies. The host application
 * implements this contract (for example by delegating to Laravel Gate/Policy)
 * and returns an explicit AuthorizationDecision.
 *
 * Fail-closed contract: if the returned value is not an AuthorizationDecision,
 * the framework treats it as an invalid decision and denies execution. The
 * native return type is intentionally omitted so that an invalid return value
 * is reported as an authorization failure instead of an uncontrolled TypeError.
 *
 * The $caller value is identity context supplied by the host application. It is
 * input to the decision, never proof of authorization by itself.
 *
 * Status: public API, approved (decision D6, ADR-0010, 2026-11-01).
 *
 * @package WaysNX\BusinessFramework\Contracts
 */
interface AuthorizerInterface
{
    /**
     * Decide whether the caller may execute the Business Function.
     *
     * @param BusinessFunctionInterface $function The function being executed
     * @param array $request The validated request
     * @param mixed $caller Host-supplied caller/identity context
     *
     * @return AuthorizationDecision
     */
    public function authorize(BusinessFunctionInterface $function, array $request, mixed $caller);
}
