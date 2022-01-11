<?php

declare(strict_types=1);

namespace LaminasTest\ApiTools\ContentValidation\TestAsset;

use Laminas\InputFilter\CollectionInputFilter;

class CustomCollectionInputFilter extends CollectionInputFilter
{
    /**
     * @param mixed $context
     */
    public function isValid($context = null): bool
    {
        return true;
    }
}
