<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\ContentValidation;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class ContentValidationListenerFactory implements FactoryInterface
{
    /**
     * Create and return a ContentValidationListener instance.
     *
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return ContentValidationListener
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $contentValidationConfig = isset($config['api-tools-content-validation']) ? $config['api-tools-content-validation'] : [];
        $restServices = $this->getRestServicesFromConfig($config);

        return new ContentValidationListener(
            $contentValidationConfig,
            $container->get('InputFilterManager'),
            $restServices
        );
    }

    /**
     * Create and return a ContentValidationListener instance (v2).
     *
     * Provided for backwards compatibility; proxies to __invoke().
     *
     * @param ServiceLocatorInterface $container
     * @return ContentValidationListener
     */
    public function createService(ServiceLocatorInterface $container)
    {
        return $this($container, ContentValidationListener::class);
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
        $restServices = [];
        if (! isset($config['api-tools-rest'])) {
            return $restServices;
        }

        foreach ($config['api-tools-rest'] as $controllerService => $restConfig) {
            if (! isset($restConfig['route_identifier_name'])) {
                continue;
            }
            $restServices[$controllerService] = $restConfig['route_identifier_name'];
        }

        return $restServices;
    }
}
