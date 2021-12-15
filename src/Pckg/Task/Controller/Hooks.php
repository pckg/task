<?php

namespace Pckg\Task\Contoller;

use Pckg\Task\Form\Hook;

class Hooks
{
    /**
     * @param Hook $hook
     * @return bool[]|void
     */
    public function postIndexAction(Hook $hook)
    {
        // return some response?
        $hook->toHookEvent()->handle();

        return [
            'success' => true,
        ];
    }
}
