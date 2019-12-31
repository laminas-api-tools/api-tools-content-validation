<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ApiTools\ContentValidation\InputFilter;

use Laminas\ApiTools\ContentValidation\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit_Framework_TestCase as TestCase;

class InputFilterAbstractFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->services = new ServiceManager();
        $this->filters  = new InputFilterPluginManager();
        $this->filters->setServiceLocator($this->services);
        $this->services->setService('InputFilterManager', $this->filters);

        $this->factory = new InputFilterAbstractServiceFactory();
    }

    public function testCannotCreateServiceIfNoConfigServicePresent()
    {
        $this->assertFalse($this->factory->canCreateServiceWithName($this->filters, 'filter', 'filter'));
    }

    public function testCannotCreateServiceIfConfigServiceDoesNotHaveInputFiltersConfiguration()
    {
        $this->services->setService('Config', array());
        $this->assertFalse($this->factory->canCreateServiceWithName($this->filters, 'filter', 'filter'));
    }

    public function testCannotCreateServiceIfConfigInputFiltersDoesNotContainMatchingServiceName()
    {
        $this->services->setService('Config', array(
            'input_filter_specs' => array(),
        ));
        $this->assertFalse($this->factory->canCreateServiceWithName($this->filters, 'filter', 'filter'));
    }

    public function testCanCreateServiceIfConfigInputFiltersContainsMatchingServiceName()
    {
        $this->services->setService('Config', array(
            'input_filter_specs' => array(
                'filter' => array(),
            ),
        ));
        $this->assertTrue($this->factory->canCreateServiceWithName($this->filters, 'filter', 'filter'));
    }

    public function testCreatesInputFilterInstance()
    {
        $this->services->setService('Config', array(
            'input_filter_specs' => array(
                'filter' => array(),
            ),
        ));
        $filter = $this->factory->createServiceWithName($this->filters, 'filter', 'filter');
        $this->assertInstanceOf('Laminas\InputFilter\InputFilterInterface', $filter);
    }

    /**
     * @depends testCreatesInputFilterInstance
     */
    public function testUsesConfiguredValidationAndFilterManagerServicesWhenCreatingInputFilter()
    {
        $filters = new FilterPluginManager();
        $filter  = function ($value) {};
        $filters->setService('foo', $filter);

        $validators = new ValidatorPluginManager();
        $validator  = $this->getMock('Laminas\Validator\ValidatorInterface');
        $validators->setService('foo', $validator);

        $this->services->setService('FilterManager', $filters);
        $this->services->setService('ValidatorManager', $validators);
        $this->services->setService('Config', array(
            'input_filter_specs' => array(
                'filter' => array(
                    'input' => array(
                        'name' => 'input',
                        'required' => true,
                        'filters' => array(
                            array( 'name' => 'foo' ),
                        ),
                        'validators' => array(
                            array( 'name' => 'foo' ),
                        ),
                    ),
                ),
            ),
        ));

        $inputFilter = $this->factory->createServiceWithName($this->filters, 'filter', 'filter');
        $this->assertTrue($inputFilter->has('input'));

        $input = $inputFilter->get('input');

        $filterChain = $input->getFilterChain();
        $this->assertSame($filters, $filterChain->getPluginManager());
        $this->assertEquals(1, count($filterChain));
        $this->assertSame($filter, $filterChain->plugin('foo'));
        $this->assertEquals(1, count($filterChain));

        $validatorChain = $input->getvalidatorChain();
        $this->assertSame($validators, $validatorChain->getPluginManager());
        $this->assertEquals(1, count($validatorChain));
        $this->assertSame($validator, $validatorChain->plugin('foo'));
        $this->assertEquals(1, count($validatorChain));
    }
}
