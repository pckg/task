<?php

namespace Pckg\Task\Controller;

use Pckg\Task\Form\Hook;

class Hooks
{
    /**
     * @param Hook $hook
     * @return bool[]|void
     */
    public function postIndexAction(Hook $hook)
    {
        $hook->toHookEvent()->handle();

        // return some response?
        return [
            'success' => true,
        ];
    }
}
