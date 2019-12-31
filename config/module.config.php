<?php // @codingStandardsIgnoreFile
/**
 * @see       https://github.com/laminas-api-tools/api-tools-content-validation for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-content-validation/blob/master/LICENSE.md New BSD License
 */

return array(
    'controller_plugins' => array(
        'invokables' => array(
            'getinputfilter' => 'Laminas\ApiTools\ContentValidation\InputFilter\InputFilterPlugin',
        ),
    ),
    'input_filter_specs' => array(
        /*
         * An array of service name => config pairs.
         *
         * Service names must be unique, and will be the name by which the
         * input filter will be retrieved. The configuration is any valid
         * configuration for an input filter, as shown in the manual:
         *
         * - http://laminas.readthedocs.org/en/latest/modules/laminas.input-filter.intro.html
         */
    ),
    'input_filters' => array(
        'abstract_factories' => array(
            'Laminas\InputFilter\InputFilterAbstractServiceFactory',
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
    'api-tools-content-validation' => array(
        'methods_without_bodies' => array(),
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
         */
    ),
);
