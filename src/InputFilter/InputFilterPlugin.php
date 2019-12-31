<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\ContentValidation\InputFilter;

use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Mvc\InjectApplicationEventInterface;

class InputFilterPlugin extends AbstractPlugin
{
    public function __invoke()
    {
        $controller = $this->getController();
        if (! $controller instanceof InjectApplicationEventInterface) {
            return null;
        }

        $event       = $controller->getEvent();
        $inputFilter = $event->getParam(__NAMESPACE__);

        if (! $inputFilter instanceof InputFilterInterface) {
            return null;
        }

        return $inputFilter;
    }
}
