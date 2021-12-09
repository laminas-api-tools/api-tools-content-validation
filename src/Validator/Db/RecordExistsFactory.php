<?php

declare(strict_types=1);

namespace Laminas\ApiTools\ContentValidation\Validator\Db;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Validator\Db\RecordExists;

class RecordExistsFactory implements FactoryInterface
{
    /**
     * Required for v2 compatibility.
     *
     * @var null|array
     */
    private $options;

    /**
     * Create and return a RecordExists validator instance.
     *
     * @param string $requestedName
     * @param null|array $options
     * @return RecordExists
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        if (isset($options['adapter'])) {
            return new RecordExists(ArrayUtils::merge(
                $options,
                ['adapter' => $container->get($options['adapter'])]
            ));
        }

        return new RecordExists($options);
    }

    /**
     * Create and return a RecordExists validator instance (v2).
     *
     * Provided for backwards compatibility; proxies to __invoke().
     *
     * @return RecordExists
     */
    public function createService(ServiceLocatorInterface $validators)
    {
        $container = $validators->getServiceLocator() ?: $validators;
        return $this($container, RecordExists::class, $this->options);
    }

    /**
     * Set options property
     *
     * Implemented for backwards compatibility.
     *
     * @param array $options
     * @return void
     */
    public function setCreationOptions(array $options)
    {
        $this->options = $options;
    }
}
