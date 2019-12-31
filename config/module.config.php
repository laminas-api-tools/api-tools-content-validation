<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

return [
    'input_filters' => [
        /*
         * An array of service name => config pairs.
         *
         * Service names must be unique, and will be the name by which the
         * input filter will be retrieved. The configuration is any valid
         * configuration for an input filter, as shown in the manual:
         *
         * - http://laminas.readthedocs.org/en/latest/modules/laminas.input-filter.intro.html
         */
    ],
    'service_manager' => [
        'abstract_factories' => [
            'Laminas\ApiTools\ContentValidation\InputFilter\InputFilterAbstractServiceFactory',
        ],
        'factories' => [
            'Laminas\ApiTools\ContentValidation\ContentValidationListener' => 'Laminas\ApiTools\ContentValidation\ContentValidationListenerFactory',
        ],
    ],
    'api-tools-content-validation' => [
        /*
         * An array of controller service name => config pairs.
         *
         * The configuration *must* include:
         *
         * - input_filter: the name of an input filter service to use with the
         *   given controller
         *
         * In the future, additional options may be added, such as the ability
         * to restrict by HTTP method, define validation groups by HTTP method,
         * etc.
         */
    ],
];
