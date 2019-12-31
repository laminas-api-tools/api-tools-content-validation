<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\ContentValidation;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class ContentValidationListenerFactory implements FactoryInterface
{
    /**
     * @param ServiceLocatorInterface $services
     * @return ContentValidationListener
     */
    public function createService(ServiceLocatorInterface $services)
    {
        $config = [];
        if ($services->has('Config')) {
            $allConfig = $services->get('Config');
            if (isset($allConfig['api-tools-content-validation'])) {
                $config = $allConfig['api-tools-content-validation'];
            }
        }
        return new ContentValidationListener($config, $services);
    }
}
