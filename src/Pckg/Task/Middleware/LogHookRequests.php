<?php

namespace Pckg\Task\Middleware;

use Pckg\Framework\Request;

class LogHookRequests
{

    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function execute(callable $next)
    {
        
        return $next();
    }

}
