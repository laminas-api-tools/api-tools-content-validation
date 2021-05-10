<?php

namespace Laminas\ApiTools\ContentValidation\Validator\Db;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Validator\Db\NoRecordExists;

class NoRecordExistsFactory implements FactoryInterface
{
    /**
     * Required for v2 compatibility.
     *
     * @var null|array
     */
    private $options;

    /**
     * Create and return a NoRecordExists validator.
     *
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param null|array $options
     * @return NoRecordExists
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        if (isset($options['adapter'])) {
            return new NoRecordExists(ArrayUtils::merge(
                $options,
                ['adapter' => $container->get($options['adapter'])]
            ));
        }

        return new NoRecordExists($options);
    }

    /**
     * Create and return a NoRecordExists validator (v2).
     *
     * Provided for backwards compatibility; proxies to __invoke().
     *
     * @param ServiceLocatorInterface $validators
     * @return NoRecordExists
     */
    public function createService(ServiceLocatorInterface $validators)
    {
        $container = $validators->getServiceLocator() ?: $validators;
        return $this($container, NoRecordExists::class, $this->options);
    }

    /**
     * Set options property
     *
     * Implemented for backwards compatibility.
     *
     * @param array $options
     */
    public function setCreationOptions(array $options)
    {
        $this->options = $options;
    }
}
