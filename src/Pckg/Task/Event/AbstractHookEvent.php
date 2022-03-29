<?php

namespace Pckg\Task\Event;

use Pckg\Task\Record\Task;
use Pckg\Task\Service\Webhook;

class AbstractHookEvent
{
    protected HookEvent $event;

    public function __construct(HookEvent $event)
    {
        $this->event = $event;
    }

    public function getEvent(): string
    {
        return $this->event->getEvent();
    }

    public function getHookEvent(): HookEvent
    {
        return $this->event;
    }

    public function getBody(): array
    {
        return $this->event->getBody();
    }

    protected function getBodyObject()
    {
        return (object)$this->getBody();
    }

    public function getRetry(): int
    {
        return $this->event->getRetry();
    }

    public function getContext(): array
    {
        return $this->event->getContext();
    }

    public function getOriginContext(string $origin, string $prop)
    {
        return collect($this->getContext())
                ->first(fn($context) => $context['origin'] === $origin && array_key_exists($prop, $context))[$prop] ?? null;
    }

    public function getLastContext(string $prop)
    {
        return collect($this->getContext())->last()[$prop] ?? null;
    }

    public function getMyContext(string $prop)
    {
        return $this->getOriginContext(config('pckg.hook.origin'), $prop);
    }

    public function getMyTask()
    {
        $task = $this->getMyContext('task');

        if (!$task) {
            return null;
        }

        return Task::gets($task);
    }

    public function when($condition, callable $callable)
    {
        if ($condition) {
            return $callable($condition);
        }

        return $condition;
    }

    public function hook(string $event, array $body, ?Task $task = null)
    {
        if (!$task) {
            $task = $this->getMyTask();
        }

        if ($task) {
            Webhook::notification($task, $event, $body);
            return;
        }

        Webhook::processNotification([
            'body' => $body,
            'context' => $this->event->getContext(),
            'task' => null,
        ]);
    }
}
