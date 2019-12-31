<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\ContentValidation\Validator\Db;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\MutableCreationOptionsInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Validator\Db\RecordExists;

class RecordExistsFactory implements FactoryInterface, MutableCreationOptionsInterface
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * Set options property
     *
     * @param array $options
     */
    public function setCreationOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * @param ServiceLocatorInterface $validators
     * @return RecordExists
     */
    public function createService(ServiceLocatorInterface $validators)
    {
        if (isset($this->options['adapter'])) {
            return new RecordExists(ArrayUtils::merge(
                $this->options,
                ['adapter' => $validators->getServiceLocator()->get($this->options['adapter'])]
            ));
        }

        return new RecordExists($this->options);
    }
}
