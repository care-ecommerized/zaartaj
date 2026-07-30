<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    /**
     * Whether the product may appear on the storefront.
     */
    public function isPublishable(): bool
    {
        return $this === self::Active;
    }
}
