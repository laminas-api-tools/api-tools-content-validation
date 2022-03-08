Laminas Content Validation
=====================

[![Build Status](https://github.com/laminas-api-tools/api-tools-content-validation/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/laminas-api-tools/api-tools-content-validation/actions/workflows/continuous-integration.yml)

> ## 🇷🇺 Русским гражданам
>
> Мы, участники Laminas, родились и живем в разных странах. У многих из нас есть друзья, родственники и коллеги как в России, так и в Украине. Некоторые из нас родились в России. Некоторые из нас живут в России. У некоторых бабушки и дедушки сражались с фашистами во Второй мировой войне. Здесь никто не поддерживает фашизм.
>
> У одного из нас есть украинская родственница, которая спаслась из дома вместе с сыном. Поезд задержался из-за бомбежки на дороге впереди. У нас есть друзья, которые прячутся в бомбоубежищах. Мы с тревогой ждем весточки от них после воздушных налетов, которые беспорядочно наносят удары и попадают по больницам, школам, детским садам и домам. Мы не берем это из каких-либо СМИ. Мы наблюдаем это напрямую.
>
> Вы доверяете нам достаточно, чтоб использовать наши программы, и мы просим вас довериться нам вновь. Мы нуждаемся в помощи. Выходите и протестуйте против этой бесполезной войны. Остановите кровопролитие. Скажите "Нет войне!"
>
> ## 🇺🇸 To Citizens of Russia
>
> We at Laminas come from all over the world. Many of us have friends, family and colleagues in both Russia and Ukraine. Some of us were born in Russia. Some of us currently live in Russia. Some have grandparents who fought Nazis in World War II. Nobody here supports fascism.
>
> One team member has a Ukrainian relative who fled her home with her son. The train was delayed due to bombing on the road ahead. We have friends who are hiding in bomb shelters. We anxiously follow up on them after the air raids, which indiscriminately fire at hospitals, schools, kindergartens and houses. We're not taking this from any media. These are our actual experiences.
>
> You trust us enough to use our software. We ask that you trust us to say the truth on this. We need your help. Go out and protest this unnecessary war. Stop the bloodshed. Say "stop the war!"

Introduction
------------

Laminas module for automating validation of incoming input.

Allows the following:

- Defining named input filters.
- Mapping named input filters to named controller services.
- Returning an `ApiProblemResponse` with validation error messages on invalid input.

Requirements
------------
  
Please see the [composer.json](composer.json) file.

Installation
------------

Run the following `composer` command:

```console
$ composer require laminas-api-tools/api-tools-content-validation
```

Alternately, manually add the following to your `composer.json`, in the `require` section:

```javascript
"require": {
    "laminas-api-tools/api-tools-content-validation": "^1.4"
}
```

And then run `composer update` to ensure the module is installed.

Finally, add the module name to your project's `config/application.config.php` under the `modules`
key:

```php
return [
    /* ... */
    'modules' => [
        /* ... */
        'Laminas\ApiTools\ContentValidation',
    ],
    /* ... */
];
```

Configuration
-------------

### User Configuration

This module utilizes two user level configuration keys `api-tools-content-validation` and also
`input_filter_specs` (named such that this functionality can be moved into Laminas in the future).

#### Service Name key

The `api-tools-content-validation` key is a mapping between controller service names as the key, and the
value being an array of mappings that determine which HTTP method to respond to and what input
filter to map to for the given request.  The keys for the mapping can either be an HTTP method that
accepts a request body (i.e., `POST`, `PUT`, `PATCH`, or `DELETE`), or it can be the word
`input_filter`. The value assigned for the `input_filter` key will be used in the case that no input
filter is configured for the current HTTP request method.

Example where there is a default as well as a POST filter:

```php
'api-tools-content-validation' => [
    'Application\Controller\HelloWorld' => [
        'input_filter' => 'Application\Controller\HelloWorld\Validator',
        'POST' => 'Application\Controller\HelloWorld\CreationValidator',
    ],
],
```

In the above example, the `Application\Controller\HelloWorld\Validator` service will be selected for
`PATCH`, `PUT`, or `DELETE` requests, while the `Application\Controller\HelloWorld\CreationValidator`will be selected for `POST` requests.

Starting in version 1.1.0, two additional keys can be defined to affect application validation
behavior:

- `use_raw_data`: if NOT present, raw data is ALWAYS injected into the "BodyParams" container (defined
  by api-tools-content-negotiation).  If this key is present and a boolean false, then the validated,
  filtered data from the input filter will be used instead.

- `allows_only_fields_in_filter`: if present, and `use_raw_data` is boolean false, the value of this
  flag will define whether or not additional fields present in the payload will be merged with the
  filtered data.
  
- `remove_empty_data`: Should we remove empty data from received data?
  - If no `remove_empty_data` flag is present, do nothing - use data as is
  - If `remove_empty_data` flag is present AND is boolean true, then remove
    empty data from current data array
  - Does not remove empty data if keys matched received data

> ### Validating GET requests
>
> - **Since 1.3.0.**
>
> Starting in 1.3.0, you may also specify `GET` as an HTTP method, mapping it to
> an input filter in order to validate your query parameters. Configuration is
> exactly as described in the above section.
>
> This feature is only available when manually configuring your API; it is not
> exposed in the Admin UI.

> ### Validating collection requests
>
> - **Since 1.5.0**
>
> Starting in 1.5.0, you may specify any of:
>
> - `POST_COLLECTION`
> - `PUT_COLLECTION`
> - `PATCH_COLLECTION`
>
> as keys. These will then be used specifically with the given HTTP method, but
> only on requests matching the collection endpoint.

> ### Validating DELETE requests
>
> - **Since 1.6.0**
>
> Starting in 1.6.0, you may specify each of the following keys for input
> filters:
>
> - `DELETE`
> - `DELETE_COLLECTION`
>
> The input filter associated with the key will be used to validate data sent in
> the request body.

#### input_filter_spec

`input_filter_spec` is for configuration-driven creation of input filters.  The keys for this array
will be a unique name, but more often based off the service name it is mapped to under the
`api-tools-content-validation` key.  The values will be an input filter configuration array, as is
described in the Laminas manual [section on input
filters](http://laminas.readthedocs.org/en/latest/modules/laminas.input-filter.intro.html).

Example:

```php
'input_filter_specs' => [
    'Application\Controller\HelloWorldGet' => [
        0 => [
            'name' => 'name',
            'required' => true,
            'filters' => [
                0 => [
                    'name' => 'Laminas\Filter\StringTrim',
                    'options' => [],
                ],
            ],
            'validators' => [],
            'description' => 'Hello to name',
            'allow_empty' => false,
            'continue_if_empty' => false,
        ],
    ],
```

### System Configuration

The following configuration is defined by the module in order to function within a Laminas application.

```php
namespace Laminas\ApiTools\ContentValidation;

use Laminas\InputFiler\InputFilterAbstractServiceFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'controller_plugins' => [
        'aliases' => [
            'getinputfilter' => InputFilter\InputFilterPlugin::class,
            'getInputfilter' => InputFilter\InputFilterPlugin::class,
            'getInputFilter' => InputFilter\InputFilterPlugin::class,
        ],
        'factories' => [
            InputFilter\InputFilterPlugin::class => InvokableFactory::class,
        ],
    ],
    'input_filters' => [
        'abstract_factories' => [
            InputFilterAbstractServiceFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            ContentValidationListener::class => ContentValidationListenerFactory::class,
        ],
    ],
    'validators' => [
        'factories' => [
            'Laminas\ApiTools\ContentValidation\Validator\DbRecordExists' => Validator\Db\RecordExistsFactory::class,
            'Laminas\ApiTools\ContentValidation\Validator\DbNoRecordExists' => Validator\Db\NoRecordExistsFactory::class,
        ],
    ],
];
```

Laminas Events
---------

### Listeners

#### Laminas\ApiTools\ContentValidation\ContentValidationListener

This listener is attached to the `MvcEvent::EVENT_ROUTE` event at priority `-650`.  Its purpose is
to utilize the `api-tools-content-validation` configuration in order to determine if the current request's
selected controller service name has a configured input filter.  If it does, it will traverse the
mappings from the configuration file to create the appropriate input filter (from configuration or
the Laminas input filter plugin manager) in order to validate the incoming data.  This
particular listener utilizes the data from the `api-tools-content-negotiation` data container in order to
get the deserialized content body parameters.

### Events

#### Laminas\ApiTools\ContentValidation\ContentValidationListener::EVENT_BEFORE_VALIDATE

This event is emitted by `Laminas\ApiTools\ContentValidation\ContentValidationListener::onRoute()`
(described above) in between aggregating data to validate and determining the
input filter, and the actual validation of data. Its purpose is to allow users:

- the ability to manipulate input filters. 
- to modify the data set to validate (available since 1.4.0).

As an example, you might want to validate an identifier provided via the URI,
and matched during routing. You may do this as follows:

```php
$events->listen(ContentValidationListener::EVENT_BEFORE_VALIDATE, function ($e) {
    if ($e->getController() !== MyRestController::class) {
        return;
    }

    $matches = $e->getRouteMatch();
    $data = $e->getParam('Laminas\ApiTools\ContentValidation\ParameterData') ?: [];
    $data['id'] = $matches->getParam('id');
    $e->setParam('Laminas\ApiTools\ContentValidation\ParameterData', $data);
});
```

Laminas Services
-----------

### Controller Plugins

#### Laminas\ApiTools\ContentValidation\InputFilter\InputFilterPlugin (aka getInputFilter)

This plugin is available to Laminas controllers. When invoked (`$this->getInputFilter()` or
`$this->plugin('getinputfilter')->__invoke()`), it returns whatever is in the MVC event parameter
`Laminas\ApiTools\ContentValidation\InputFilter`, returning null for any value that is not an implementation of
`Laminas\InputFilter\InputFilter`.

### Service

#### Laminas\InputFilter\InputFilterAbstractServiceFactory

This abstract factory is responsible for creating and returning an appropriate input filter given
a name and the configuration from the top-level key `input_filter_specs`. It is registered with
`Laminas\InputFilter\InputFilterPluginManager`.
