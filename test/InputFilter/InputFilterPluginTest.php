<?php

declare(strict_types=1);

namespace LaminasTest\ApiTools\ContentValidation\InputFilter;

use Laminas\ApiTools\ContentValidation\InputFilter\InputFilterPlugin;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use PHPUnit\Framework\TestCase;

class InputFilterPluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->event = $event = new MvcEvent();

        $controller = $this->getMockBuilder(AbstractController::class)->getMock();
        $controller->expects($this->any())
            ->method('getEvent')
            ->will($this->returnValue($event));

        $this->plugin = new InputFilterPlugin();
        $this->plugin->setController($controller);
    }

    public function testMissingInputFilterParamInEventCausesPluginToYieldNull(): void
    {
        $this->assertNull($this->plugin->__invoke());
    }

    public function testInvalidTypeInEventInputFilterParamCausesPluginToYieldNull(): void
    {
        $this->event->setParam('Laminas\ApiTools\ContentValidation\InputFilter', (object) ['foo' => 'bar']);
        $this->assertNull($this->plugin->__invoke());
    }

    public function testValidInputFilterInEventIsReturnedByPlugin(): void
    {
        $inputFilter = $this->getMockBuilder(InputFilterInterface::class)->getMock();
        $this->event->setParam('Laminas\ApiTools\ContentValidation\InputFilter', $inputFilter);
        $this->assertSame($inputFilter, $this->plugin->__invoke());
    }
}
