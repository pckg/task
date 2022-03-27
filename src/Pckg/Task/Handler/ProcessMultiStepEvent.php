<?php

namespace Pckg\Task\Handler;

use Pckg\Task\Event\AbstractHookEvent;
use Pckg\Task\Record\Task;
use Pckg\Task\Service\Webhook;

class ProcessMultiStepEvent
{
    public function __construct(protected AbstractHookEvent $event)
    {

    }

    public function handle()
    {
        $procedure = $this->event->getMyContext('procedure');
        if (!$procedure) {
            return;
        }

        $nextTask = collect($procedure)->first(fn($task) => in_array($this->event->getEvent() . '@' . $this->event->getLastContext('origin'), $task['when'] ?? []));
        if (!$nextTask) {
            return;
        }

        if (!isset($nextTask['hook'])) {
            // complete? error?
            return;
        }

        $task = context()->getOrDefault(Task::class);
        if (!$task) {
            return;
        }

        // trigger next task
        // queue('hook-notifications', ['task' => $task->id, 'hook' => $nextTask['hook'], 'body' => $nextTask['body']]);
        $hook = $nextTask['hook'];
        if (!is_array($hook)) {
            Webhook::notification($task, $hook, $nextTask['body'] ?? []);
            return;
        }

        foreach ($hook as $origin => $event) {
            Webhook::notification($task, $event, $nextTask['body'] ?? [], [$origin]);
        }
    }
}
