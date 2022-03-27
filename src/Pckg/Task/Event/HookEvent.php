<?php

namespace Pckg\Task\Event;

use Pckg\Concept\Context;
use Pckg\Task\Form\Hook;
use Pckg\Task\Handler\ProcessMultiStepEvent;
use Pckg\Task\Record\Task;
use Pckg\Task\Service\Webhook;

class HookEvent
{
    protected string $origin;
    protected string $event;
    protected array $body;
    protected array $context;
    protected int $retry = 0;

    public function __construct(string $origin, string $event, array $body, array $context, int $retry = 0)
    {
        $this->origin = $origin;
        $this->event = $event;
        $this->body = $body;
        $this->context = $context;
        $this->retry = $retry;
    }

    public function handle()
    {
        $origin = collect(config('pckg.hook.origins', []))
            ->first(fn($origin, $key) => $origin['alias'] === $this->origin);

        if (!$origin) {
            error_log('Non-registered origin ' . $this->origin);
            return;
        }

        // allow wrapping
        $genericEvent = new GenericHookEvent($this);
        trigger(HookEvent::class . '.handling', [$genericEvent, $this]);

        $this->handleTriggers($origin['triggers'][$this->event] ?? []);

        (new ProcessMultiStepEvent($genericEvent))->handle();

        $this->handleForwarders($origin['forwarders'][$this->event] ?? []);

        // allow after-events
        trigger(HookEvent::class . '.handled', [$genericEvent, $this]);
    }

    /**
     * @param $events string|array
     * @return void
     */
    protected function handleTriggers($events)
    {
        if (!$events) {
            return;
        }

        if (!is_array($events)) {
            $events = [$events];
        }

        foreach ($events as $event) {
            // queue('hook-events', ['event' => $this->toArray()]);
            // this should be queued?
            $handler = (new $event($this));

            // handle the event
            $handler->handle();
        }
    }

    protected function handleForwarders(array $forwards)
    {
        if (!$forwards) {
            return;
        }

        if (!is_associative_array($forwards)) {
            $forwards = [$forwards];
        }

        foreach ($forwards as $forward) {

            $genericEvent = new GenericHookEvent($this);
            $task = $genericEvent->getMyContext('task');

            // allow wrapping
            trigger(HookEvent::class . '.forwarding', [$genericEvent, $this]);

            try {
                // is task in context?

                if (!$task) {
                    // we need to provide task so the context is known?
                    error_log("No task to forward? " . json_encode($this->toArray()));

                    Webhook::processNotification([
                        'origin' => config('pckg.hook.origin'),
                        'event' => explode('@', $forward['to'])[0],
                        'body' => is_only_callable($forward['body']) ? $forward['body']() : $forward['body'],
                        'context' => $this->context,
                        'retry' => 0,
                        'task' => null,
                    ],
                        $forward['to']
                    );
                } else {
                    Webhook::notification(
                        $task,
                        $forward['to'],
                        $forward['body']
                    );
                }
            } catch (\Throwable $e) {
                error_log("Error forwarding task:" . exception($e));
            }

            // allow after-events
            trigger(HookEvent::class . '.forwarded', [$genericEvent, $this]);
        }
    }

    public function getTask(): ?Task
    {
        return context()->getOrDefault(Task::class);
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getRetry(): int
    {
        return $this->retry;
    }

    public function toArray(): array
    {
        return [
            'origin' => $this->origin,
            'event' => $this->event,
            'body' => $this->body,
            'context' => $this->context,
            'retry' => $this->retry,
        ];
    }
}
