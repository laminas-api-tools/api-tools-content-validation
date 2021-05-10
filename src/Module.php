<?php

namespace Laminas\ApiTools\ContentValidation;

use Laminas\Mvc\MvcEvent;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(MvcEvent $e)
    {
        $app      = $e->getApplication();
        $events   = $app->getEventManager();
        $services = $app->getServiceManager();

        $services->get(ContentValidationListener::class)->attach($events);
    }
}
