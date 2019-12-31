Laminas Content Validation
=====================

[![Build Status](https://travis-ci.org/laminas-api-tools/api-tools-content-validation.png)](https://travis-ci.org/laminas-api-tools/api-tools-content-validation)

Introduction
------------

Laminas Module for automating validation of incoming input.

Allows the following:

- Defining named input filters.
- Mapping named input filters to named controller services.
- Returning an `ApiProblemResponse` with validation error messages on invalid input.

Installation
------------

Run the following `composer` command:

```console
$ composer require "laminas-api-tools/api-tools-content-validation:~1.0-dev"
```

Alternately, manually add the following to your `composer.json`, in the `require` section:

```javascript
"require": {
    "laminas-api-tools/api-tools-content-validation": "~1.0-dev"
}
```

And then run `composer update` to ensure the module is installed.

Finally, add the module name to your project's `config/application.config.php` under the `modules`
key:

```php
return array(
    /* ... */
    'modules' => array(
        /* ... */
        'Laminas\ApiTools\ContentValidation',
    ),
    /* ... */
);
```

Configuration
=============

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

Example where there is a default as well as a GET filter:

```php
'api-tools-content-validation' => array(
    'Application\Controller\HelloWorld' => array(
        'input_filter' => 'Application\Controller\HelloWorld\Validator',
        'POST' => 'Application\Controller\HelloWorld\CreationValidator',
    ),
),
```

In the above example, the `Application\Controller\HelloWorld\Validator` service will be selected for
`PATCH`, `PUT`, or `DELETE` requests, while the `Application\Controller\HelloWorld\CreationValidator`will be selected for `POST` requests.

#### `input_filter_spec`

`input_filter_spec` is for configuration-driven creation of input filters.  The keys for this array
will be a unique name, but more often based off the service name it is mapped to under the
`api-tools-content-validation` key.  The values will be an input filter configuration array, as is
described in the Laminas manual [section on input
filters](http://laminas.readthedocs.org/en/latest/modules/laminas.input-filter.intro.html).

Example:

```php
'input_filter_specs' => array(
    'Application\Controller\HelloWorldGet' => array(
        0 => array(
            'name' => 'name',
            'required' => true,
            'filters' => array(
                0 => array(
                    'name' => 'Laminas\Filter\StringTrim',
                    'options' => array(),
                ),
            ),
            'validators' => array(),
            'description' => 'Hello to name',
            'allow_empty' => false,
            'continue_if_empty' => false,
        ),
    ),
```

### System Configuration

The following configuration is defined by the module in order to function within a Laminas application.

```php
'input_filters' => array(
    'abstract_factories' => array(
        'Laminas\ApiTools\ContentValidation\InputFilter\InputFilterAbstractServiceFactory',
    ),
),
'service_manager' => array(
    'factories' => array(
        'Laminas\ApiTools\ContentValidation\ContentValidationListener' => 'Laminas\ApiTools\ContentValidation\ContentValidationListenerFactory',
    ),
),
'validators' => array(
    'factories' => array(
        'Laminas\ApiTools\ContentValidation\Validator\DbRecordExists' => 'Laminas\ApiTools\ContentValidation\Validator\Db\RecordExistsFactory',
        'Laminas\ApiTools\ContentValidation\Validator\DbNoRecordExists' => 'Laminas\ApiTools\ContentValidation\Validator\Db\NoRecordExistsFactory',
    ),
),
```

Laminas Events
==========

### Listeners

#### `Laminas\ApiTools\ContentValidation\ContentValidationListener`

This listener is attached to the `MvcEvent::EVENT_ROUTE` event at priority `-650`.  Its purpose is
to utilize the `api-tools-content-validation` configuration in order to determine if the current request's
selected controller service name has a configured input filter.  If it does, it will traverse the
mappings from the configuration file to create the appropriate input filter (from configuration or
the Laminas input filter plugin manager) in order to validate the incoming data.  This
particular listener utilizes the data from the `api-tools-content-negotiation` data container in order to
get the deserialized content body parameters.

Laminas Services
============

### Service

#### `Laminas\ApiTools\ContentValidation\InputFilter\InputFilterAbstractServiceFactory`

This abstract factory is responsible for creating and returning an appropriate input filter given
a name and the configuration from the top-level key `input_filter_specs`. It is registered with
`Laminas\InputFilter\InputFilterPluginManager`.
