<?php

namespace Pckg\Task\Middleware;

use Pckg\Framework\Config;
use Pckg\Framework\Request;

class DisallowInvalidHosts
{
    public Request $request;

    public Config $config;

    public function __construct(Request $request, Config $config)
    {
        $this->request = $request;
        $this->config = $config;
    }

    public function execute(callable $next)
    {
        $ip = $this->request->clientIp();
        $origins = $this->config->get('pckg.hook.origins', []);
        $found = collect($origins)->first(fn($origin) => $origin['ip'] === $ip);

        if ($found) {
            return $next();
        }

        throw new \Exception('Invalid hook origin ' . $ip);
    }
}
