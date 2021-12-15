<?php

namespace Pckg\Task\Provider;

use Pckg\Task\Contoller\Hooks as HooksController;
use Pckg\Task\Form\Hook;
use Pckg\Task\Handler\ProcessHook;
use Pckg\Task\Middleware\DisallowInvalidHosts;
use Pckg\Framework\Provider;
use Pckg\Task\Record\Task;

class Hooks extends Provider
{
    public function routes()
    {
        return [
            routeGroup([
                'controller' => HooksController::class,
                'routePrefix' => '/api/hooks',
                'namePrefix' => 'api.hooks'
            ], [
                '' => route()->middlewares([
                    DisallowInvalidHosts::class,
                ]),
            ]),
        ];
    }
}
