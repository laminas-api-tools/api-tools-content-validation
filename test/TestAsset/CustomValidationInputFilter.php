<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ApiTools\ContentValidation\TestAsset;

use Laminas\InputFilter\InputFilter;

class CustomValidationInputFilter extends InputFilter
{
    public function isValid($context = null)
    {
        return true;
    }
}
