<?php

namespace Laminas\ApiTools\ContentValidation;

use Laminas\InputFilter\InputFilterAbstractServiceFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'controller_plugins' => [
        'aliases' => [
            'getinputfilter' => InputFilter\InputFilterPlugin::class,
            'getInputfilter' => InputFilter\InputFilterPlugin::class,
            'getInputFilter' => InputFilter\InputFilterPlugin::class,

            // Legacy Zend Framework aliases
            \ZF\ContentValidation\InputFilter\InputFilterPlugin::class => InputFilter\InputFilterPlugin::class,
        ],
        'factories' => [
            InputFilter\InputFilterPlugin::class => InvokableFactory::class,
        ],
    ],
    'input_filter_specs' => [
        /*
         * An array of service name => config pairs.
         *
         * Service names must be unique, and will be the name by which the
         * input filter will be retrieved. The configuration is any valid
         * configuration for an input filter, as shown in the manual:
         *
         * - https://docs.laminas.dev/laminas-inputfilter/intro/
         */
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
            'Laminas\ApiTools\ContentValidation\Validator\DbRecordExists'
                => Validator\Db\RecordExistsFactory::class,
            'Laminas\ApiTools\ContentValidation\Validator\DbNoRecordExists'
                => Validator\Db\NoRecordExistsFactory::class,
        ],
    ],
    'api-tools-content-validation' => [
        'methods_without_bodies' => [],
        /*
         * An array of controller service name => config pairs.
         *
         * The configuration *must* include at least *one* of:
         *
         * - input_filter: the name of an input filter service to use with the
         *   given controller, AND/OR
         *
         * - a key named after one of the HTTP methods POST, PATCH, or PUT. The
         *   value must be the name of an input filter service to use.
         *
         * When determining an input filter to use, precedence will be given to
         * any configured for specific HTTP methods, and will fall back to the
         * "input_filter" key, when defined, otherwise. If none can be determined,
         * the module will assume no validation is defined, and that the content
         * provided is valid.
         *
         * Additionally, you can define either of the following two keys to
         * further define application validation behavior:
         *
         * - use_raw_data: if NOT present, raw data is ALWAYS injected into
         *   the "BodyParams" container (defined by api-tools-content-negotiation).
         *   If this key is present and a boolean false, then the validated,
         *   filtered data from the input filter will be used instead.
         *
         * - allows_only_fields_in_filter: if present, and use_raw_data is
         *   boolean false, the value of this flag will define whether or
         *   not additional fields present in the payload will be merged
         *   with the filtered data.
         *
         * - remove_empty_data: Should we remove empty data from received data?
         *   - If no `remove_empty_data` flag is present, do nothing - use data as is
         *   - If `remove_empty_data` flag is present AND is boolean true, then remove
         *     empty data from current data array
         */
    ],
];
