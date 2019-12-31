Laminas Content Validation
=====================

[![Build Status](https://travis-ci.org/laminas-api-tools/api-tools-content-validation.png)](https://travis-ci.org/laminas-api-tools/api-tools-content-validation)
[![Coverage Status](https://coveralls.io/repos/laminas-api-tools/api-tools-content-validation/badge.png?branch=master)](https://coveralls.io/r/laminas-api-tools/api-tools-content-validation)

Module for automating validation of incoming input within a Laminas
application.

Allows the following:

- Defining named input filters
- Mapping named input filters to named controller services
- Returning an `ApiProblemResponse` with validation error messages on invalid
  input

Installation
------------

You can install using:

```
curl -s https://getcomposer.org/installer | php
php composer.phar install
```
