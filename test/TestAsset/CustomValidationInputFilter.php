<?php

namespace LaminasTest\ApiTools\ContentValidation\TestAsset;

use Laminas\InputFilter\InputFilter;

class CustomValidationInputFilter extends InputFilter
{
    public function isValid($context = null)
    {
        return true;
    }
}
