<?php

namespace Pckg\Task\Provider;

use Pckg\Task\Controller\Hooks as HooksController;
use Pckg\Task\Event\HookEvent;
use Pckg\Task\Form\Hook;
use Pckg\Task\Handler\ProcessHook;
use Pckg\Task\Handler\ProcessMultiStepEvent;
use Pckg\Task\Handler\UnwrapHookEvent;
use Pckg\Task\Handler\WrapHookEvent;
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
                'urlPrefix' => '/api/hooks',
                'namePrefix' => 'api.hooks'
            ], [
                '' => route()->middlewares([
                    DisallowInvalidHosts::class,
                ]),
            ]),
        ];
    }

    public function listeners()
    {
        return [
            HookEvent::class . '.handling' => [
                WrapHookEvent::class,
            ],
            HookEvent::class . '.handled' => [
                ProcessMultiStepEvent::class,
                UnwrapHookEvent::class,
            ],
        ];
    }
}
