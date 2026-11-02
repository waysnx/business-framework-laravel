<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Authorization;

/**
 * AuthorizationDecision
 *
 * Explicit, immutable outcome of an authorization check.
 *
 * WBF authorization fails closed (Phase 0 decision D6): a Business Function
 * executes only when an authorizer returns an AuthorizationDecision whose
 * isAllowed() is true. Any other outcome (no authorizer, a non-decision
 * return value, or a deny decision) blocks execution.
 *
 * Status: public API, approved (decision D6, ADR-0010, 2026-11-01).
 *
 * @package WaysNX\BusinessFramework\Authorization
 */
final class AuthorizationDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $reason
    ) {
    }

    /**
     * Create an explicit allow decision.
     */
    public static function allow(string $reason = ''): self
    {
        return new self(true, $reason);
    }

    /**
     * Create an explicit deny decision.
     */
    public static function deny(string $reason = 'Access denied'): self
    {
        return new self(false, $reason);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }
}
