<?php

namespace LaminasTest\ApiTools\ContentValidation;

use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\ApiTools\ContentNegotiation\ParameterDataContainer;
use Laminas\ApiTools\ContentValidation\ContentValidationListener;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Filter\StringTrim;
use Laminas\Http\Request as HttpRequest;
use Laminas\InputFilter\CollectionInputFilter;
use Laminas\InputFilter\Factory as InputFilterFactory;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Router\RouteMatch as V2RouteMatch;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\Parameters;
use Laminas\Stdlib\Request as StdlibRequest;
use PHPUnit\Framework\TestCase;

class ContentValidationListenerTest extends TestCase
{
    public function createRouteMatch(array $params = [])
    {
        $class = class_exists(V2RouteMatch::class) ? V2RouteMatch::class : RouteMatch::class;
        return new $class($params);
    }

    public function testAttachesToRouteEventAtLowPriority()
    {
        $listener = new ContentValidationListener();
        $events = $this->getMockBuilder(EventManagerInterface::class)->getMock();
        $events->expects($this->once())
            ->method('attach')
            ->with(
                $this->equalTo(MvcEvent::EVENT_ROUTE),
                $this->equalTo([$listener, 'onRoute']),
                $this->lessThan(-99)
            );
        $listener->attach($events);
    }

    public function testReturnsEarlyIfRequestIsNonHttp()
    {
        $listener = new ContentValidationListener();

        $request = new StdlibRequest();
        $event   = new MvcEvent();
        $event->setRequest($request);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function nonBodyMethods()
    {
        return [
            'get'     => ['GET'],
            'head'    => ['HEAD'],
            'options' => ['OPTIONS'],
        ];
    }

    public function testAddCustomMethods()
    {
        $className = ContentValidationListener::class;
        $listener = $this->getMockBuilder($className)
                ->disableOriginalConstructor()
                ->getMock();

        $listener->expects($this->at(0))->method('addMethodWithoutBody')->with('LINK');
        $listener->expects($this->at(1))->method('addMethodWithoutBody')->with('UNLINK');

        $reflectedClass = new \ReflectionClass($className);
        $constructor = $reflectedClass->getConstructor();
        $constructor->invoke($listener, [
            'methods_without_bodies' => [
                'LINK',
                'UNLINK',
            ],
        ]);
    }

    /**
     * @dataProvider nonBodyMethods
     */
    public function testReturnsEarlyIfRequestMethodWillNotContainRequestBody($method)
    {
        $listener = new ContentValidationListener();

        $request = new HttpRequest();
        $request->setMethod($method);
        $event   = new MvcEvent();
        $event->setRequest($request);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsNullIfCollectionRequestWithoutBodyIsValid()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['GET' => 'FooValidator'],
        ], $services, ['Foo' => 'foo_id']);

        $request = new HttpRequest();
        $request->setMethod('GET');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setQueryParams([
            'foo' => 123,
            'bar' => 'abc',
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsApiProblemResponseIfCollectionRequestWithoutBodyIsInvalid()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['GET' => 'FooValidator'],
        ], $services, ['Foo' => 'foo_id']);

        $request = new HttpRequest();
        $request->setMethod('GET');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setQueryParams([
            'foo' => 'abc',
            'bar' => 123,
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertNotContains('Value is required and can\'t be empty', $response->getBody());
        $this->assertContains('The input must contain only digits', $response->getBody());
        $this->assertContains('The input does not match against pattern \'/^[a-z]+/i\'', $response->getBody());

        return $response;
    }

    public function testReturnsNullIfEntityRequestWithoutBodyIsValid()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['GET' => 'FooValidator'],
        ], $services, ['Foo' => 'foo_id']);

        $request = new HttpRequest();
        $request->setMethod('GET');

        $matches = $this->createRouteMatch(['controller' => 'Foo', 'foo_id' => 3]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setQueryParams([
            'foo' => 123,
            'bar' => 'abc',
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsApiProblemResponseIfEntityRequestWithoutBodyIsInvalid()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['GET' => 'FooValidator'],
        ], $services, ['Foo' => 'foo_id']);

        $request = new HttpRequest();
        $request->setMethod('GET');

        $matches = $this->createRouteMatch(['controller' => 'Foo', 'foo_id' => 3]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setQueryParams([
            'foo' => 'abc',
            'bar' => 123,
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertNotContains('Value is required and can\'t be empty', $response->getBody());
        $this->assertContains('The input must contain only digits', $response->getBody());
        $this->assertContains('The input does not match against pattern \'/^[a-z]+/i\'', $response->getBody());

        return $response;
    }

    public function testReturnsNullIfEntityRequestWithoutBodyIsValidAndUndefinedFieldsAreAllowed()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener(
            [
                'Foo' => [
                    'GET' => 'FooValidator',
                    'allows_only_fields_in_filter' => false,
                ],
            ],
            $services,
            ['Foo' => 'foo_id']
        );

        $request = new HttpRequest();
        $request->setMethod('GET');

        $matches = $this->createRouteMatch(['controller' => 'Foo', 'foo_id' => 3]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setQueryParams([
            'foo' => 123,
            'bar' => 'xyz',
            'undefined' => 'value',
        ]);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsApiProblemResponseIfEntityRequestWithoutBodyIsInvalidAndUnknownFieldsAreDisallowed()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener(
            [
                'Foo' => [
                    'GET' => 'FooValidator',
                    'allows_only_fields_in_filter' => true,
                ],
            ],
            $services,
            ['Foo' => 'foo_id']
        );

        $request = new HttpRequest();
        $request->setMethod('GET');

        $matches = $this->createRouteMatch(['controller' => 'Foo', 'foo_id' => 3]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setQueryParams([
            'foo' => 123,
            'bar' => 'xyz',
            'undefined' => 'value',
        ]);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertContains('Unrecognized fields: undefined', $response->getBody());
    }

    public function testReturnsNullIfCollectionRequestWithoutBodyIsValidAndUndefinedFieldsAreAllowed()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener(
            [
                'Foo' => [
                    'GET' => 'FooValidator',
                    'allows_only_fields_in_filter' => false,
                ],
            ],
            $services,
            ['Foo' => 'foo_id']
        );

        $request = new HttpRequest();
        $request->setMethod('GET');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setQueryParams([
            'foo' => 123,
            'bar' => 'xyz',
            'undefined' => 'value',
        ]);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsApiProblemResponseIfCollectionRequestWithoutBodyIsInvalidAndUnknownFieldsAreDisallowed()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener(
            [
                'Foo' => [
                    'GET' => 'FooValidator',
                    'allows_only_fields_in_filter' => true,
                ],
            ],
            $services,
            ['Foo' => 'foo_id']
        );

        $request = new HttpRequest();
        $request->setMethod('GET');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setQueryParams([
            'foo' => 123,
            'bar' => 'xyz',
            'undefined' => 'value',
        ]);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertContains('Unrecognized fields: undefined', $response->getBody());
    }

    public function testReturnsEarlyIfNoRouteMatchesPresent()
    {
        $listener = new ContentValidationListener();

        $request = new HttpRequest();
        $request->setMethod('POST');
        $event   = new MvcEvent();
        $event->setRequest($request);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsEarlyIfRouteMatchesDoNotContainControllerService()
    {
        $listener = new ContentValidationListener();

        $request = new HttpRequest();
        $request->setMethod('POST');
        $matches = $this->createRouteMatch([]);
        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    public function testReturnsEarlyIfControllerServiceIsNotInConfig()
    {
        $listener = new ContentValidationListener();

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([]);

        $request = new HttpRequest();
        $request->setMethod('POST');
        $matches = $this->createRouteMatch(['controller' => 'Foo']);
        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }
    /**
     * @dataProvider listMethods
     */
    public function testSeparateCollectionInputFilterValidation($method)
    {
        $services = new ServiceManager();
        $factory = new InputFilterFactory();
        $services->setService(
            'FooValidatorCollection',
            $factory->createInputFilter(
                [
                    'bar' => [
                        'required' => true,
                        'name' => 'bar',
                        'validators' => [],
                    ],
                ]
            )
        );
        $listener = new ContentValidationListener(
            [
                'Foo' => [
                    $method . '_COLLECTION' => 'FooValidatorCollection',
                ],
            ],
            $services,
            ['Foo' => 'foo_id']
        );


        $request = new HttpRequest();
        $request->setMethod($method);

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = array_fill(
            0,
            3,
            ['bar' => '']
        );
        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event = new MvcEvent();
        $event->setName('route');
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $problem = $response->getApiProblem();
        $asArray = $problem->toArray();
        $this->assertArrayHasKey('validation_messages', $asArray);
        $this->assertCount(3, $asArray['validation_messages']);

        $this->assertArrayHasKey('bar', $asArray['validation_messages'][0]);
        $this->assertInternalType('array', $asArray['validation_messages'][0]['bar']);
        $this->assertArrayHasKey('bar', $asArray['validation_messages'][1]);
        $this->assertInternalType('array', $asArray['validation_messages'][1]['bar']);
        $this->assertArrayHasKey('bar', $asArray['validation_messages'][2]);
        $this->assertInternalType('array', $asArray['validation_messages'][2]['bar']);
    }

    public function testReturnsApiProblemResponseIfContentNegotiationBodyDataIsMissing()
    {
        $services = new ServiceManager();
        $services->setService('FooValidator', new InputFilter());
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('POST');
        $matches = $this->createRouteMatch(['controller' => 'Foo']);
        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        return $response;
    }

    /**
     * @depends testReturnsApiProblemResponseIfContentNegotiationBodyDataIsMissing
     */
    public function testMissingContentNegotiationDataHas500Response($response)
    {
        $this->assertEquals(500, $response->getApiProblem()->status);
    }

    public function testReturnsApiProblemResponseIfInputFilterServiceIsInvalid()
    {
        $services = new ServiceManager();
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertEquals(500, $response->getApiProblem()->status);
    }

    public function testReturnsNothingIfContentIsValid()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([
            'foo' => 123,
            'bar' => 'abc',
        ]);

        $event   = new MvcEvent();
        $event->setName('route');
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
        return $event;
    }

    public function testReturnsApiProblemResponseIfContentIsInvalid()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([
            'foo' => 'abc',
            'bar' => 123,
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        return $response;
    }

    /**
     * @depends testReturnsApiProblemResponseIfContentIsInvalid
     */
    public function testApiProblemResponseFromInvalidContentHas422Status($response)
    {
        $this->assertEquals(422, $response->getApiProblem()->status);
    }

    /**
     * @depends testReturnsApiProblemResponseIfContentIsInvalid
     */
    public function testApiProblemResponseFromInvalidContentContainsValidationErrorMessages($response)
    {
        $problem = $response->getApiProblem();
        $asArray = $problem->toArray();
        $this->assertArrayHasKey('validation_messages', $asArray);
        $this->assertCount(2, $asArray['validation_messages']);
        $this->assertArrayHasKey('foo', $asArray['validation_messages']);
        $this->assertInternalType('array', $asArray['validation_messages']['foo']);
        $this->assertArrayHasKey('bar', $asArray['validation_messages']);
        $this->assertInternalType('array', $asArray['validation_messages']['bar']);
    }

    public function testReturnsApiProblemResponseIfParametersAreMissing()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([
            'foo' => 123,
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        return $response;
    }

    public function testAllowsValidationOfPartialSetsForPatchRequests()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('PATCH');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([
            'foo' => 123,
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
    }

    /**
     * @dataProvider listMethods
     */
    public function testPatchWithZeroRouteIdDoesNotEmitANoticeAndDoesNotHaveCollectionInputFilterWhenRequestHasABody(
        $verb
    ) {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService(
            'FooValidator',
            $factory->createInputFilter(
                [
                    'foo' => [
                        'name'     => 'foo',
                        'required' => false,
                    ],
                ]
            )
        );
        $listener = new ContentValidationListener(
            [
                'Foo' => ['input_filter' => 'FooValidator'],
            ],
            $services,
            [
                'Foo' => 'foo_id',
            ]
        );

        $request = new HttpRequest();
        $request->setMethod($verb);

        $matches = $this->createRouteMatch(['controller' => 'Foo']);
        $matches->setParam('foo_id', "0");

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams(
            [
                'foo' => 123,
            ]
        );

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $inputFilter = $event->getParam('Laminas\ApiTools\ContentValidation\InputFilter');
        // with notices on, this won't get hit with broken code
        $this->assertNotInstanceOf(CollectionInputFilter::class, $inputFilter);
    }

    /**
     * @dataProvider listMethods
     */
    public function testPatchWithZeroRouteIdWithNoRequestBodyDoesNotHaveCollectionInputFilter($verb)
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService(
            'FooValidator',
            $factory->createInputFilter(
                [
                    'foo' => [
                        'name'     => 'foo',
                        'required' => false,
                    ],
                ]
            )
        );
        $listener = new ContentValidationListener(
            [
                'Foo' => ['input_filter' => 'FooValidator'],
            ],
            $services,
            [
                'Foo' => 'foo_id',
            ]
        );

        $request = new HttpRequest();
        $request->setMethod($verb);

        $matches = $this->createRouteMatch(['controller' => 'Foo']);
        $matches->setParam('foo_id', "0");

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([]);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);
        $this->assertNull($listener->onRoute($event));
        $inputFilter = $event->getParam('Laminas\ApiTools\ContentValidation\InputFilter');
        $this->assertNotInstanceOf(CollectionInputFilter::class, $inputFilter);
    }

    public function testFailsValidationOfPartialSetsForPatchRequestsThatIncludeUnknownInputs()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('PATCH');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([
            'foo' => 123,
            'baz' => 'who cares?',
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        return $response;
    }

    public function testFailsValidationOfPartialSetsForPatchRequestsThatIncludeBlankFieldNames()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('PATCH');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([
            '' => true,
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $innerProblem = $response->getApiProblem();
        $this->assertEquals(400, $innerProblem->status);
        $this->assertEquals('Unrecognized field ""', $innerProblem->detail);
    }

    /**
     * @depends testFailsValidationOfPartialSetsForPatchRequestsThatIncludeUnknownInputs
     */
    public function testInvalidValidationGroupIs400Response($response)
    {
        $this->assertEquals(400, $response->getApiProblem()->status);
    }

    /**
     * @depends testReturnsNothingIfContentIsValid
     */
    public function testInputFilterIsInjectedIntoMvcEvent($event)
    {
        $inputFilter = $event->getParam('Laminas\ApiTools\ContentValidation\InputFilter');
        $this->assertInstanceOf(InputFilter::class, $inputFilter);
    }

    /**
     * @group api-tools-skeleton-43
     */
    public function testPassingOnlyDataNotInInputFilterShouldInvalidateRequest()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'first_name' => [
                'name' => 'first_name',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'Laminas\Validator\NotEmpty',
                        'options' => ['breakchainonfailure' => true],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([
            'foo' => 'abc',
            'bar' => 123,
        ]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        return $response;
    }

    public function httpMethodSpecificInputFilters()
    {
        return [
            'post-valid' => [
                'POST',
                ['post' => 123],
                true,
                'PostValidator',
            ],
            'post-invalid' => [
                'POST',
                ['post' => 'abc'],
                false,
                'PostValidator',
            ],
            'post-invalid-property' => [
                'POST',
                ['foo' => 123],
                false,
                'PostValidator',
            ],
            'patch-valid' => [
                'PATCH',
                ['patch' => 123],
                true,
                'PatchValidator',
            ],
            'patch-invalid' => [
                'PATCH',
                ['patch' => 'abc'],
                false,
                'PatchValidator',
            ],
            'patch-invalid-property' => [
                'PATCH',
                ['foo' => 123],
                false,
                'PatchValidator',
            ],
            'put-valid' => [
                'PUT',
                ['put' => 123],
                true,
                'PutValidator',
            ],
            'put-invalid' => [
                'PUT',
                ['put' => 'abc'],
                false,
                'PutValidator',
            ],
            'put-invalid-property' => [
                'PUT',
                ['foo' => 123],
                false,
                'PutValidator',
            ],
        ];
    }

    public function configureInputFilters($services)
    {
        $inputFilterFactory = new InputFilterFactory();
        $services->setService('PostValidator', $inputFilterFactory->createInputFilter([
            'post' => [
                'name' => 'post',
                'required' => true,
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
        ]));

        $services->setService('PatchValidator', $inputFilterFactory->createInputFilter([
            'patch' => [
                'name' => 'patch',
                'required' => true,
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
        ]));

        $services->setService('PutValidator', $inputFilterFactory->createInputFilter([
            'put' => [
                'name' => 'put',
                'required' => true,
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
        ]));
    }

    /**
     * @group method-specific
     * @dataProvider httpMethodSpecificInputFilters
     */
    public function testCanFetchHttpMethodSpecificInputFilterWhenValidating(
        $method,
        array $data,
        $expectedIsValid,
        $filterName
    ) {
        $services = new ServiceManager();
        $this->configureInputFilters($services);

        $listener = new ContentValidationListener([
            'Foo' => [
                'POST'  => 'PostValidator',
                'PATCH' => 'PatchValidator',
                'PUT'   => 'PutValidator',
            ],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod($method);

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($data);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $result = $listener->onRoute($event);

        // Ensure input filter discovered is the same one we expect
        $inputFilter = $event->getParam('Laminas\ApiTools\ContentValidation\InputFilter');
        $this->assertInstanceOf(InputFilterInterface::class, $inputFilter);
        $this->assertSame($services->get($filterName), $inputFilter);

        // Ensure we have a response we expect
        if ($expectedIsValid) {
            $this->assertNull($result);
            $this->assertNull($event->getResponse());
        } else {
            $this->assertInstanceOf(ApiProblemResponse::class, $result);
        }
    }

    public function testMergesFilesArrayIntoDataPriorToValidationWhenFilesArrayIsPopulated()
    {
        $validator = $this->getMockBuilder(InputFilterInterface::class)->getMock();
        $services = new ServiceManager();
        $services->setService('FooValidator', $validator);

        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $files = new Parameters([
            'foo' => [
                0 => [
                    'file' => [
                        'name' => 'foo.txt',
                        'type' => 'text/plain',
                        'size' => 1,
                        'tmp_name' => '/tmp/foo.txt',
                        'error' => UPLOAD_ERR_OK,
                    ],
                ],
            ],
        ]);
        $data = [
            'bar' => 'baz',
            'quz' => 'quuz',
            'foo' => [
                0 => [
                    'bar' => 'baz',
                ],
            ],
        ];
        $dataContainer = new ParameterDataContainer();
        $dataContainer->setBodyParams($data);

        $request = new HttpRequest();
        $request->setMethod('POST');
        $request->setFiles($files);

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataContainer);

        $validator->expects($this->any())
            ->method('has')
            ->with($this->equalTo('FooValidator'))
            ->will($this->returnValue(true));
        $validator->expects($this->once())
            ->method('setData')
            ->with($this->equalTo(ArrayUtils::merge($data, $files->toArray(), true)));
        $validator->expects($this->once())
            ->method('isValid')
            ->will($this->returnValue(true));

        $this->assertNull($listener->onRoute($event));
    }

    public function listMethods()
    {
        return [
            'PUT'    => ['PUT'],
            'PATCH'  => ['PATCH'],
        ];
    }

    /**
     * @dataProvider listMethods
     * @group 3
     */
    public function testCanValidateCollections($method)
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));

        // Create ContentValidationListener with rest controllers populated
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod($method);

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();

        $params = array_fill(0, 10, [
            'foo' => 123,
            'bar' => 'abc',
        ]);

        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    /**
     * @group 3
     * @dataProvider listMethods
     */
    public function testReturnsApiProblemResponseForCollectionIfAnyFieldsAreInvalid($method)
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod($method);

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = array_fill(0, 10, [
            'foo' => '123a',
        ]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
    }

    /**
     * @group 3
     */
    public function testValidatesPatchToCollectionWhenFieldMissing()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('PATCH');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = array_fill(0, 10, [
            'foo' => 123,
        ]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertNull($response);
    }

    /**
     * @group 3
     */
    public function testCanValidatePostedCollections()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = array_fill(0, 10, [
            'foo' => 123,
            'bar' => 'abc',
        ]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertNull($response);
    }

    public function testValidatePostedCollectionsAndAllowedOnlyFieldsFromFilterReturnsApiProblemWithUnrecognizedFields()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener(
            [
                'Foo' => [
                    'input_filter' => 'FooValidator',
                    'allows_only_fields_in_filter' => true,
                ],
            ],
            $services,
            ['Foo' => 'foo_id']
        );

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            [
                'foo' => 123,
                'bar' => 'abc',
            ],
            [
                'foo' => 345,
                'bar' => 'baz',
                'unknown' => 'value',
                'other' => 'abc',
            ],
            [
                'foo' => 678,
                'bar' => 'oui',
            ],
            [
                'foo' => 988,
                'bar' => 'com',
                'key' => 'xyz',
            ],
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertContains('Unrecognized fields: [1: unknown, other], [3: key]', $response->getBody());
    }

    /**
     * @group 3
     */
    public function testReportsValidationFailureForPostedCollection()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = array_fill(0, 10, [
            'foo' => 'abc',
            'bar' => 123,
        ]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertEquals(422, $response->getApiProblem()->status);
    }

    /**
     * @group 3
     */
    public function testValidatesPostedEntityWhenCollectionIsPossibleForService()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            'foo' => 123,
            'bar' => 'abc',
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertNull($response);
    }

    /**
     * @group 3
     */
    public function testIndicatesInvalidPostedEntityWhenCollectionIsPossibleForService()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            'foo' => 'abc',
            'bar' => 123,
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertEquals(422, $response->getApiProblem()->status);
    }

    /**
     * @group 29
     */
    public function testSaveFilteredDataIntoDataContainer()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooFilter', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'filters' => [
                    ['name' => 'StringTrim'],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => [
                'input_filter' => 'FooFilter',
                'use_raw_data' => false,
            ],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            'foo' => ' abc ',
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertEquals('abc', $dataParams->getBodyParam('foo'));
    }

    /**
     * @group 29
     */
    public function testShouldSaveFilteredDataWhenRequiredEvenIfInputFilterIsNotUnknownInputsCapable()
    {
        $services = new ServiceManager();
        $inputFilter = $this->getMockBuilder(InputFilterInterface::class)->getMock();
        $inputFilter->expects($this->any())
            ->method('setData')
            ->willReturn($this->returnValue(null));
        $inputFilter->expects($this->any(''))
            ->method('isValid')
            ->will($this->returnValue(true));
        $inputFilter->expects($this->any(''))
            ->method('getValues')
            ->will($this->returnValue(['foo' => 'abc']));

        $factory  = new InputFilterFactory();
        $services->setService('FooFilter', $inputFilter);
        $listener = new ContentValidationListener([
            'Foo' => [
                'input_filter' => 'FooFilter',
                'use_raw_data' => false,
            ],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            'foo' => ' abc ',
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertEquals('abc', $dataParams->getBodyParam('foo'));
    }


    /**
     * @group 29
     */
    public function testSaveRawDataIntoDataContainer()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooFilter', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'filters' => [
                    ['name' => 'StringTrim'],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooFilter', 'use_raw_data' => true],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            'foo' => ' abc ',
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $listener->onRoute($event);
        $this->assertEquals(' abc ', $dataParams->getBodyParam('foo'));
    }

    /**
     * @group 29
     */
    public function testTrySaveUnknownData()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooFilter', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'filters' => [
                    ['name' => 'StringTrim'],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => [
                'input_filter' => 'FooFilter',
                'allows_only_fields_in_filter' => true,
                'use_raw_data' => false,
            ],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            'foo' => ' abc ',
            'unknown' => 'value'
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $result = $listener->onRoute($event);

        $this->assertInstanceOf(ApiProblemResponse::class, $result);
        $apiProblemData = $result->getApiProblem()->toArray();
        $this->assertEquals(422, $apiProblemData['status']);
        $this->assertContains('Unrecognized fields', $apiProblemData['detail']);
    }

    /**
     * @group 29
     */
    public function testUnknownDataMustBeMergedWithFilteredData()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooFilter', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'filters' => [
                    ['name' => 'StringTrim'],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => [
                'input_filter' => 'FooFilter',
                'allows_only_fields_in_filter' => false,
                'use_raw_data' => false,
            ],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            'foo' => ' abc ',
            'unknown' => 'value'
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $result = $listener->onRoute($event);
        $this->assertNotInstanceOf(ApiProblemResponse::class, $result);
        $this->assertEquals('abc', $dataParams->getBodyParam('foo'));
        $this->assertEquals('value', $dataParams->getBodyParam('unknown'));
    }

    /**
     * @group 65
     */
    public function testUseRawAndAllowOnlyFieldsInFilterData()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooFilter', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'filters' => [
                    ['name' => 'StringTrim'],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => [
                'input_filter' => 'FooFilter',
                'allows_only_fields_in_filter' => true,
                'use_raw_data' => true,
            ],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            'foo' => ' abc ',
            'unknown' => 'value'
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $result = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $result);
        $this->assertEquals(422, $result->getApiProblem()->status);
        $this->assertEquals('Unrecognized fields: unknown', $result->getApiProblem()->detail);
    }

    /**
     * @group 29
     */
    public function testSaveUnknownDataWhenEmptyInputFilter()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooFilter', $factory->createInputFilter([]));
        $listener = new ContentValidationListener([
            'Foo' => [
                'input_filter' => 'FooFilter',
                'use_raw_data' => false,
            ],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            'foo' => ' abc ',
            'unknown' => 'value'
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $listener->onRoute($event);

        $this->assertEquals($params, $dataParams->getBodyParams());
    }

    /**
     * @group 40 removeEmptyData
     * @param array $eventParams
     * @return MvcEvent
     */
    public function createGroup40Event(array $eventParams)
    {
        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($eventParams);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        return $event;
    }

    /**
     * @group 40 removeEmptyData
     * @return ContentValidationListener
     */
    public function createGroup40Listener()
    {
        $factory = new InputFilterFactory();

        $inputFilterA = $factory->createInputFilter([
            [
                'name'     => 'foo',
                'required' => true,
                'filters'  => [
                    ['name' => StringTrim::class],
                ],
            ],
            [
                'name'     => 'bar',
                'required' => false,
                'filters'  => [
                    ['name' => StringTrim::class],
                ],
            ],
            [
                'name'     => 'empty',
                'required' => false,
                'filters'  => [
                    ['name' => StringTrim::class],
                ],
            ],
        ]);

        $inputFilterB = $factory->createInputFilter([
            [
                'name'     => 'empty_field',
                'required' => false,
                'filters'  => [
                    ['name' => StringTrim::class],
                ],
            ],
        ]);

        $inputFilterA->add($inputFilterB, 'empty_array');

        $services = new ServiceManager();
        $services->setService('FooFilter', $inputFilterA);

        return new ContentValidationListener(
            [
                'Foo' => [
                    'input_filter'                 => 'FooFilter',
                    'use_raw_data'                 => false,
                    'allows_only_fields_in_filter' => true,
                    'remove_empty_data'            => true,
                ],
            ],
            $services,
            [
                'Foo' => 'foo_id',
            ]
        );
    }

    /**
     * Flows:
     * 1 - data is empty, return immediately
     * 2 - loop key/value - value (is not an array and (not empty or (is a boolean & not in comparison array)))
     * 3 - loop key/value - value is not an array
     * 4 - after filtering value, value is empty
     * 5 - value is an array containing recursive data (subject to 1 through 4)
     *
     * This test does #1
     *
     * @group 40 removeEmptyData
     */
    public function testFilterEmptyEntriesFromDataByOptionWhenDataEmpty()
    {
        // empty array
        $event = $this->createGroup40Event([]);

        $listener = $this->createGroup40Listener();
        $listener->onRoute($event);

        $this->assertEquals(
            [],
            $event->getParam('LaminasContentNegotiationParameterData')->getBodyParams()
        );
    }

    public function booleanProvider()
    {
        yield 'true'  => [true];
        yield 'false' => [false];
    }

    /**
     * Flows:
     * 1 - data is empty, return immediately
     * 2 - loop key/value - value (is not an array and (not empty or (is a boolean & not in comparison array)))
     * 3 - loop key/value - value is not an array
     * 4 - after filtering value, value is empty
     * 5 - value is an array containing recursive data (subject to 1 through 4)
     *
     * This test does #2 (twice, once for 'true', once for 'false')
     *
     * @group 40 removeEmptyData
     * @dataProvider booleanProvider
     * @param bool $value
     */
    public function testFilterEmptyEntriesFromDataByOptionWhenValueBooleanNotInComparison($value)
    {
        $event = $this->createGroup40Event([
            'foo' => $value,
        ]);

        $listener = $this->createGroup40Listener();
        $listener->onRoute($event);

        $this->assertEquals(
            [
                'foo' => $value,
            ],
            $event->getParam('LaminasContentNegotiationParameterData')->getBodyParams()
        );
    }

    /**
     * Flows:
     * 1 - data is empty, return immediately
     * 2 - loop key/value - value (is not an array and (not empty or (is a boolean & not in comparison array)))
     * 3 - loop key/value - value is not an array
     * 4 - after filtering value, value is empty
     * 5 - value is an array containing recursive data (subject to 1 through 4)
     *
     * This test does #3
     *
     * @group 40 removeEmptyData
     */
    public function testFilterEmptyEntriesFromDataByOptionWhenValueNotAnArray()
    {
        $event = $this->createGroup40Event([
            'foo' => ' string ',
        ]);

        $listener = $this->createGroup40Listener();
        $listener->onRoute($event);

        $this->assertEquals(
            [
                'foo' => 'string',
            ],
            $event->getParam('LaminasContentNegotiationParameterData')->getBodyParams()
        );
    }

    /**
     * Flows:
     * 1 - data is empty, return immediately
     * 2 - loop key/value - value (is not an array and (not empty or (is a boolean & not in comparison array)))
     * 3 - loop key/value - value is not an array
     * 4 - after filtering value, value is empty
     * 5 - value is an array containing recursive data (subject to 1 through 4)
     *
     * This test does #4
     *
     * @group 40 removeEmptyData
     */
    public function testFilterEmptyEntriesFromDataByOptionWhenValueEmptyAfterFilter()
    {
        $event = $this->createGroup40Event([
            'foo' => [
                'test' => [],
            ],
        ]);

        $listener = $this->createGroup40Listener();
        $listener->onRoute($event);

        $this->assertEquals(
            [],
            $event->getParam('LaminasContentNegotiationParameterData')->getBodyParams()
        );
    }

    /**
     * Flows:
     * 1 - data is empty, return immediately
     * 2 - loop key/value - value (is not an array and (not empty or (is a boolean & not in comparison array)))
     * 3 - loop key/value - value is not an array
     * 4 - after filtering value, value is empty
     * 5 - value is an array containing recursive data (subject to 1 through 4)
     *
     * This test does #5
     *
     * @group 40 removeEmptyData
     */
    public function testFilterEmptyEntriesFromDataByOptionWithNestedData()
    {
        $event = $this->createGroup40Event([
            'foo'         => ' abc ',
            'empty'       => null,
            'empty_array' => [
                'empty_field' => null,
            ],
        ]);

        $listener = $this->createGroup40Listener();

        $listener->onRoute($event);

        // field "foo" should show filters have run; value ' abc ' should be filtered with trim to 'abc'
        // field "bar" will be added by running the filters, should be removed by remoteEmptyData
        // field "empty" should no longer be here
        // fieldset "empty_array" should be removed entirely as it's contents are also empty

        $this->assertEquals(
            [
                'foo' => 'abc',
            ],
            $event->getParam('LaminasContentNegotiationParameterData')->getBodyParams()
        );
    }

    /**
     * @dataProvider listMethods
     * @group 19
     */
    public function testDoesNotAttemptToValidateAnEntityAsACollection($method)
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));

        // Create ContentValidationListener with rest controllers populated
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod($method);

        $matches = $this->createRouteMatch([
            'controller' => 'Foo',
            'foo_id'     => uniqid(),
        ]);

        $dataParams = new ParameterDataContainer();

        $params = [
            'foo' => 123,
            'bar' => 'abc',
        ];

        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    /**
     * @group 20
     */
    public function testEmptyPostShouldReturnValidationError()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([]);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertEquals(422, $response->getApiProblem()->status);
    }

    /**
     * @group event
     */
    public function testTriggeredEventBeforeValidate()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ]
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $eventManager = new EventManager();
        $listener->setEventManager($eventManager);

        $hasRun = false;
        $eventManager->attach(
            ContentValidationListener::EVENT_BEFORE_VALIDATE,
            function (MvcEvent $e) use (&$hasRun) {
                $this->assertInstanceOf(
                    InputFilterInterface::class,
                    $e->getParam('Laminas\ApiTools\ContentValidation\InputFilter')
                );
                $hasRun = true;
            }
        );

        $request = new HttpRequest();
        $request->setMethod('PUT');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = array_fill(0, 10, [
            'foo' => '123',
        ]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertTrue($hasRun);
        $this->assertEmpty($response);
    }

    /**
     * @group event
     */
    public function testTriggeredEventBeforeValidateReturnsApiProblemResponseFromApiProblem()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ]
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $eventManager = new EventManager();
        $listener->setEventManager($eventManager);

        $hasRun = false;
        $eventManager->attach(
            ContentValidationListener::EVENT_BEFORE_VALIDATE,
            function (MvcEvent $e) use (&$hasRun) {
                $this->assertInstanceOf(
                    InputFilterInterface::class,
                    $e->getParam('Laminas\ApiTools\ContentValidation\InputFilter')
                );
                $hasRun = true;
                return new ApiProblem(422, 'Validation failed');
            }
        );

        $request = new HttpRequest();
        $request->setMethod('PUT');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = array_fill(0, 10, [
            'foo' => '123',
        ]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertTrue($hasRun);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertEquals(422, $response->getApiProblem()->status);
        $this->assertEquals('Validation failed', $response->getApiProblem()->detail);
    }


    /**
     * @group event
     */
    public function testTriggeredEventBeforeValidateReturnsApiProblemResponseFromCallback()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ]
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $eventManager = new EventManager();
        $listener->setEventManager($eventManager);

        $hasRun = false;
        $eventManager->attach(
            ContentValidationListener::EVENT_BEFORE_VALIDATE,
            function (MvcEvent $e) use (&$hasRun) {
                $this->assertInstanceOf(
                    InputFilterInterface::class,
                    $e->getParam('Laminas\ApiTools\ContentValidation\InputFilter')
                );
                $hasRun = true;
                return new ApiProblemResponse(new ApiProblem(422, 'Validation failed'));
            }
        );

        $request = new HttpRequest();
        $request->setMethod('PUT');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = array_fill(0, 10, [
            'foo' => '123',
        ]);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event   = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($event);
        $this->assertTrue($hasRun);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertEquals(422, $response->getApiProblem()->status);
        $this->assertEquals('Validation failed', $response->getApiProblem()->detail);
    }

    public function indexedFields()
    {
        return [
            'flat-array'   => [[['foo'], ['bar']]],
            'nested-array' => [[['foo' => 'abc', 'bar' => 'baz']]],
        ];
    }

    /**
     * This is testing a scenario from api-tools-admin.
     *
     * In that module, the InputFilterInputFilter defines no fields, and overrides
     * isValid() to test that the provided data describes an input filter that can
     * be created by the input filter factory.
     *
     * What we observed is that the data was being duplicated, as the data and the
     * unknown values were identical.
     *
     * @dataProvider indexedFields
     */
    public function testWhenNoFieldsAreDefinedAndValidatorPassesIndexedArrayDataShouldNotBeDuplicated($params)
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooFilter', new TestAsset\CustomValidationInputFilter());
        $listener = new ContentValidationListener([
            'Foo' => [
                'input_filter' => 'FooFilter',
            ],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));

        $bodyParams = $dataParams->getBodyParams();
        $this->assertEquals($params, $bodyParams);
    }

    /**
     * @depends testReturnsNothingIfContentIsValid
     */
    public function testEventNameShouldBeResetToOriginalOnCompletionOfListener($event)
    {
        $this->assertEquals('route', $event->getName());
    }

    public function testCollectionDeleteRequestWithBody()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));

        $listener = new ContentValidationListener([
            'Foo' => ['DELETE_COLLECTION' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('DELETE');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $params = [
            0 => [
                'foo' => 'abc',
                'bar' => 123,
            ],
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $e   = new MvcEvent();
        $e->setRequest($request);
        $e->setRouteMatch($matches);
        $e->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($e);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertEquals(422, $response->getApiProblem()->status);

        $error_messages = $response->getApiProblem()->validation_messages;
        $this->assertArrayHasKey('0', $error_messages);
        $this->assertArrayHasKey('foo', $error_messages[0]);
        $this->assertCount(1, $error_messages[0]['foo']);
        $this->assertNotContains('Value is required and can\'t be empty', $error_messages[0]['foo']);
        $this->assertContains('The input must contain only digits', $error_messages[0]['foo']);

        $this->assertArrayHasKey('bar', $error_messages[0]);
        $this->assertCount(1, $error_messages[0]['bar']);
        $this->assertNotContains('Value is required and can\'t be empty', $error_messages[0]['bar']);
        $this->assertContains('The input does not match against pattern \'/^[a-z]+/i\'', $error_messages[0]['bar']);
    }

    public function testDeleteRequestWithBody()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));

        $listener = new ContentValidationListener([
            'Foo' => ['DELETE' => 'FooValidator'],
        ], $services, [
            'Foo' => 'foo_id',
        ]);

        $request = new HttpRequest();
        $request->setMethod('DELETE');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);
        $matches->setParam('foo_id', "1");

        $params = [
            'foo' => 'abc',
            'bar' => 123,
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $e   = new MvcEvent();
        $e->setRequest($request);
        $e->setRouteMatch($matches);
        $e->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $response = $listener->onRoute($e);
        $this->assertInstanceOf(ApiProblemResponse::class, $response);
        $this->assertEquals(422, $response->getApiProblem()->status);

        $error_messages = $response->getApiProblem()->validation_messages;
        $this->assertArrayHasKey('foo', $error_messages);
        $this->assertCount(1, $error_messages['foo']);
        $this->assertNotContains('Value is required and can\'t be empty', $error_messages['foo']);
        $this->assertContains('The input must contain only digits', $error_messages['foo']);

        $this->assertArrayHasKey('bar', $error_messages);
        $this->assertCount(1, $error_messages['bar']);
        $this->assertNotContains('Value is required and can\'t be empty', $error_messages['bar']);
        $this->assertContains('The input does not match against pattern \'/^[a-z]+/i\'', $error_messages['bar']);
    }

    public function testReturnsNothingOnDeleteRequestIfContentIsInValidAndValidationSetViaInputFilterKeyword()
    {
        $services = new ServiceManager();
        $factory  = new InputFilterFactory();
        $services->setService('FooValidator', $factory->createInputFilter([
            'foo' => [
                'name' => 'foo',
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'bar' => [
                'name' => 'bar',
                'validators' => [
                    [
                        'name'    => 'Regex',
                        'options' => ['pattern' => '/^[a-z]+/i'],
                    ],
                ],
            ],
        ]));
        $listener = new ContentValidationListener([
            'Foo' => ['input_filter' => 'FooValidator'],
        ], $services);

        $request = new HttpRequest();
        $request->setMethod('DELETE');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams([
            'foo' => 'not digit data',
            'bar' => 'valid data',
        ]);

        $event = new MvcEvent();
        $event->setName('route');
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertNull($event->getResponse());
    }

    /**
     * @see https://github.com/zfcampus/zf-content-validation/issues/104
     */
    public function testRemoveEmptyDataIsNotSetSoEmptyDataAreNotRemoved()
    {
        $services = new ServiceManager();
        $factory = new InputFilterFactory();
        $services->setService('FooFilter', $factory->createInputFilter([]));

        $listener = new ContentValidationListener([
            'Foo' => [
                'input_filter' => 'FooFilter',
                'use_raw_data' => true,
            ],
        ], $services, []);

        $request = new HttpRequest();
        $request->setMethod('POST');

        $matches = $this->createRouteMatch(['controller' => 'Foo']);
        $params = [
            'str' => '',
            'foo' => null,
            'bar' => true,
            'baz' => false,
        ];

        $dataParams = new ParameterDataContainer();
        $dataParams->setBodyParams($params);

        $event = new MvcEvent();
        $event->setRequest($request);
        $event->setRouteMatch($matches);
        $event->setParam('LaminasContentNegotiationParameterData', $dataParams);

        $this->assertNull($listener->onRoute($event));
        $this->assertSame(
            [
                'str' => '',
                'foo' => null,
                'bar' => true,
                'baz' => false,
            ],
            $dataParams->getBodyParams()
        );
    }
}
