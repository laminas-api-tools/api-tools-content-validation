<?php

declare(strict_types=1);

namespace Laminas\ApiTools\ContentValidation;

use Laminas\Mvc\MvcEvent;

class Module
{
    /** @return array */
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    /** @return void */
    public function onBootstrap(MvcEvent $e)
    {
        $app      = $e->getApplication();
        $events   = $app->getEventManager();
        $services = $app->getServiceManager();

        $services->get(ContentValidationListener::class)->attach($events);
    }
}
