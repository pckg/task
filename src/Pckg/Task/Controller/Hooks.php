<?php

namespace Pckg\Task\Controller;

use Pckg\Task\Form\Hook;
use Pckg\Task\Handler\ProcessHookEvent;

class Hooks
{
    /**
     * @param Hook $hook
     * @return bool[]|void
     */
    public function postIndexAction(Hook $hook)
    {
        $hookEvent = $hook->toHookEvent();

        response()->respondAndContinue([
            'success' => true,
            'async' => true,
        ]);

        (new ProcessHookEvent($hookEvent))->handle();
    }
}
