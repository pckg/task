<?php

namespace Pckg\Task\Handler;

use Pckg\Task\Event\AbstractHookEvent;
use Pckg\Task\Event\HookEvent;
use Pckg\Task\Service\Webhook;

class ProcessHookEvent
{
    protected HookEvent $event;

    public function __construct(HookEvent $event)
    {
        $this->event = $event;
    }

    public function handle()
    {
        $origin = $this->getEventOriginConfig();

        if (!$origin) {
            error_log('Non-registered origin ' . $this->event->getOrigin());
            return;
        }

        $this->handleTriggers($origin['triggers'][$this->event->getEvent()] ?? []);
        $this->handleForwarders($origin['forwarders'][$this->event->getEvent()] ?? []);
        $this->handleProcedures();
    }

    protected function handleTriggers($events)
    {
        collect(is_array($events) ? $events : [$events])
            ->try($es, fn($e) => error_log('Error handling trigger ' . exception($e)))
            ->each(function ($event) {
                $handler = (new $event($this->event));

                trigger(HookEvent::class . '.handling', [$handler, new AbstractHookEvent($this->event)]);

                // handle the event
                try {
                    $handler->handle();
                } catch (\Throwable $e) {
                    error_log('Error handling trigger ' . exception($e));
                }

                trigger(HookEvent::class . '.handled', [$handler, new AbstractHookEvent($this->event)]);
            });
    }

    protected function handleForwarders($forwards)
    {
        collect(is_array($forwards) ? $forwards : [$forwards])
            ->try($es, fn($e) => error_log("Error forwarding task:" . exception($e)))
            ->each(fn($to) => Webhook::processNotification([
                'body' => $this->event->getBody(),
                'context' => $this->event->getContext(),
                'task' => null,
            ], $to));
    }

    protected function handleProcedures()
    {
        $event = new AbstractHookEvent($this->event);
        $task = $event->getMyTask();
        if (!$task) {
            return;
        }

        $procedure = $task->procedure;
        if (!$procedure || !$procedure->count()) {
            error_log('No procedure for task #' . $task->id);
            return;
        }

        $nextTasks = $task->getNextTasks($this);

        if (!$nextTasks->count()) {
            error_log('No next task ' . json_encode($this->event->toArray()));
            return;
        }

        $nextTasks->try($es, fn($e) => error_log(exception($e)))
            ->each(fn($nextTask) => $task->processProcedure($this->event, $nextTask));
    }

    public function getEventOriginConfig()
    {
        return collect(config('pckg.hook.origins', []))
            ->first(fn($origin, $key) => $origin['alias'] === $this->event->getOrigin());
    }
}