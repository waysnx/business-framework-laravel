<?php

declare(strict_types=1);

namespace WaysNX\BusinessFramework\Exceptions;

/**
 * ResourceNotFoundException
 *
 * A referenced BUSINESS resource does not exist (category ResourceNotFound →
 * result status not_found). Use only for business resources (e.g. an order or
 * employee referenced by the request). Framework artifact lookups such as
 * registry *NotFoundException are not business resources and are classified
 * as execution_failure (decision D1).
 *
 * @package WaysNX\BusinessFramework\Exceptions
 */
class ResourceNotFoundException extends \RuntimeException implements CategorizedExceptionInterface
{
    public function __construct(
        private string $resourceType,
        private string $resourceId,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message !== '' ? $message : "{$resourceType} '{$resourceId}' not found",
            404,
            $previous
        );
    }

    public function getCategory(): string
    {
        return self::CATEGORY_RESOURCE_NOT_FOUND;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }
}
