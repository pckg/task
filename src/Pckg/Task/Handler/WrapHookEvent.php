<?php

namespace Pckg\Task\Handler;

use Pckg\Concept\Context;
use Pckg\Task\Event\AbstractHookEvent;
use Pckg\Task\Record\Task;

class WrapHookEvent
{
    protected AbstractHookEvent $event;
    protected Context $context;

    public function __construct(AbstractHookEvent $event, Context $context)
    {
        $this->event = $this->event;
        $this->context = $this->context;
    }

    public function handle()
    {
        $taskId = $this->event->getMyContext('task')['id'] ?? null;

        if (!$taskId) {
            return;
        }

        $task = Task::gets($taskId);
        if (!$task) {
            return;
        }

        $prevTask = $this->context->getOrDefault(Task::class);

        $this->context->bind(Task::class, $task);
        $this->context->bind(Task::class . '.previous', $prevTask);
    }
}
