<?php

namespace Pckg\Task\Handler;

use Pckg\Concept\Context;
use Pckg\Task\Event\AbstractHookEvent;
use Pckg\Task\Record\Task;

class UnwrapHookEvent
{
    protected AbstractHookEvent $event;
    protected Context $context;

    public function __construct(AbstractHookEvent $event, Context $context)
    {
        $this->event = $event;
        $this->context = $context;
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

        $prevTask = $this->context->getOrDefault(Task::class . '.previous');

        $this->context->bind(Task::class, $prevTask);
        $this->context->unbind(Task::class . '.previous');
    }
}
