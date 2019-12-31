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
        $contentValidationConfig = array();
        $restServices            = array();

        if ($services->has('Config')) {
            $config = $services->get('Config');
            if (isset($config['api-tools-content-validation'])) {
                $contentValidationConfig = $config['api-tools-content-validation'];
            }
            $restServices = $this->getRestServicesFromConfig($config);
        }

        return new ContentValidationListener(
            $contentValidationConfig,
            $services->get('InputFilterManager'),
            $restServices
        );
    }

    /**
     * Generate the list of REST services for the listener
     *
     * Looks for api-tools-rest configuration, and creates a list of controller
     * service / identifier name pairs to pass to the listener.
     *
     * @param array $config
     * @return array
     */
    protected function getRestServicesFromConfig(array $config)
    {
        $restServices = array();
        if (!isset($config['api-tools-rest'])) {
            return $restServices;
        }

        foreach ($config['api-tools-rest'] as $controllerService => $restConfig) {
            if (!isset($restConfig['route_identifier_name'])) {
                continue;
            }
            $restServices[$controllerService] = $restConfig['route_identifier_name'];
        }

        return $restServices;
    }
}
