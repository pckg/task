<?php

namespace Pckg\Task\Handler;

use Pckg\Concept\Context;
use Pckg\Task\Event\AbstractHookEvent;
use Pckg\Task\Record\Task;

class WrapHookEvent
{
    public function __construct(protected AbstractHookEvent $event, protected Context $context)
    {
    }

    public function handle()
    {
        $taskId = $this->event->getMyContext('task') ?? null;

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
