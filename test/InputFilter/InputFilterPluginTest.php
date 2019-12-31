<?php

namespace LaminasTest\ApiTools\ContentValidation\InputFilter;

use Laminas\ApiTools\ContentValidation\InputFilter\InputFilterPlugin;
use Laminas\Mvc\MvcEvent;
use PHPUnit_Framework_TestCase as TestCase;

class InputFilterPluginTest extends TestCase
{
    public function setUp()
    {
        $this->event = $event = new MvcEvent();

        $controller = $this->getMock('Laminas\Mvc\Controller\AbstractController');
        $controller->expects($this->any())
            ->method('getEvent')
            ->will($this->returnValue($event));

        $this->plugin = new InputFilterPlugin();
        $this->plugin->setController($controller);
    }

    public function testMissingInputFilterParamInEventCausesPluginToYieldNull()
    {
        $this->assertNull($this->plugin->__invoke());
    }

    public function testInvalidTypeInEventInputFilterParamCausesPluginToYieldNull()
    {
        $this->event->setParam('Laminas\ApiTools\ContentValidation\InputFilter', (object) array('foo' => 'bar'));
        $this->assertNull($this->plugin->__invoke());
    }

    public function testValidInputFilterInEventIsReturnedByPlugin()
    {
        $inputFilter = $this->getMock('Laminas\InputFilter\InputFilterInterface');
        $this->event->setParam('Laminas\ApiTools\ContentValidation\InputFilter', $inputFilter);
        $this->assertSame($inputFilter, $this->plugin->__invoke());
    }
}
