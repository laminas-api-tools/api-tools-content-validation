<?php

namespace LaminasTest\ApiTools\ContentValidation\Validator\Db;

use Laminas\Db\Adapter\Adapter;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\Db\NoRecordExists;
use Laminas\Validator\ValidatorPluginManager;
use PHPUnit\Framework\TestCase;

class NoRecordExistsFactoryTest extends TestCase
{
    protected $validators;

    protected $adapter;

    protected function setUp()
    {
        parent::setUp();

        $config = include __DIR__ . '/../../../config/module.config.php';

        $this->adapter = $this->prophesize(Adapter::class)->reveal();

        $serviceManager = new ServiceManager();
        $serviceManager->setFactory('CustomAdapter', function () {
            return $this->adapter;
        });

        $this->validators = new ValidatorPluginManager($serviceManager, $config['validators']);
    }

    public function testCreateValidatorWithAdapter()
    {
        $options = [
            'adapter' => 'CustomAdapter',
            'table' => 'my_table',
            'field' => 'my_field',
        ];

        /** @var NoRecordExists $validator */
        $validator = $this->validators->get('Laminas\ApiTools\ContentValidation\Validator\DbNoRecordExists', $options);

        $this->assertInstanceOf(NoRecordExists::class, $validator);
        $this->assertEquals($this->adapter, $validator->getAdapter());
        $this->assertEquals('my_table', $validator->getTable());
        $this->assertEquals('my_field', $validator->getField());
    }

    public function testCreateValidatorWithoutAdapter()
    {
        $options = [
            'table' => 'my_table',
            'field' => 'my_field',
        ];

        /** @var NoRecordExists $validator */
        $validator = $this->validators->get('Laminas\ApiTools\ContentValidation\Validator\DbNoRecordExists', $options);

        $this->assertInstanceOf(NoRecordExists::class, $validator);
        $this->assertEquals(null, $validator->getAdapter());
        $this->assertEquals('my_table', $validator->getTable());
        $this->assertEquals('my_field', $validator->getField());
    }
}
