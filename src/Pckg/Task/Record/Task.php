<?php

namespace Pckg\Task\Record;

use Pckg\Collection;
use Pckg\Database\Entity;
use Pckg\Database\Field\JsonArray;
use Pckg\Database\Field\JsonObject;
use Pckg\Database\Record;
use Pckg\Task\Entity\Tasks;
use Pckg\Task\Event\HookEvent;
use Pckg\Task\Form\Hook;
use Pckg\Task\Service\Webhook;
use Throwable;

/**
 * @property JsonArray $procedure
 * @property JsonArray $context
 * @property string $status
 * @property string $timeouts_at
 * @property ?Task $parent
 * @property Task $lastParent
 * @property int $parent_id
 */
class Task extends Record
{
    protected $entity = Tasks::class;

    /**
     * @var ?callable
     */
    protected $make;

    /**
     * @var null|mixed
     */
    protected $result = null;

    protected $encapsulate = [
        'props' => JsonObject::class,
        'context' => JsonArray::class,
        'procedure' => JsonArray::class,
    ];

    protected static function getRequestContext()
    {
        /**
         * No context in non-http context.
         */
        if (!isHttp()) {
            return [];
        }

        /**
         * Check if the request is a valid HookEvent / Hook request.
         */
        $hookRequest = (new Hook())->initFields()->populateFromRequest();

        /**
         * A non-pckg-app origin.
         */
        if (!$hookRequest->isValid()) {
            return post('@context', []);
        }

        /**
         * Pass current context.
         */
        return $hookRequest->getData()['context'] ?? [];
    }

    public static function createFromRequest(string $title)
    {
        return static::create([
            'title' => $title,
            'context' => static::getRequestContext(),
        ]);
    }

    /**
     * @param string $name
     * @throws \Exception
     */
    public static function named(string $name, array $context = []): Task
    {
        return static::create([
            'title' => $name,
            'context' => $context,
        ]);
    }

    public static function procedure(string $name, array $context, array $procedure)
    {
        $task = static::named($name)
            ->async('10minutes')
            ->pushContext($context)
            ->setAndSave([
                'procedure' => $procedure
            ]);

        return $task;
    }

    /**
     * @param null|array|string $data
     * @param Entity|null $entity
     * @return Task
     * @throws \Exception
     * @deprecated
     * @see named
     */
    public static function create($data = [], Entity $entity = null): Task
    {
        /**
         * Get current task.
         */
        $parentTask = context()->getOrDefault(Task::class);

        /**
         * This is where we should also get the context from the external task?
         */

        /**
         * If title was passed, transform it.
         */
        if (is_string($data)) {
            $data = [
                'title' => $data,
                'context' => [],
            ];
        }

        /**
         * Create task in database.
         */
        $task = parent::create(array_merge($data, [
            'parent_id' => $parentTask->id ?? null,
            'status' => 'created',
        ]), $entity);

        /**
         * Update context in most parent task?
         */
        if (!$parentTask) {
            $task->pushContext($task->taskContext);
        }

        /**
         * Bind it as current.
         */
        context()->bind(Task::class, $task);

        return $task;
    }

    /**
     * @return array
     */
    public function getTaskContextAttribute()
    {
        return [
            'origin' => config('pckg.hook.origin'),
            'task' => $this->id,
            // add signature?
        ];
    }

    /**
     * @param array $newContext
     * @return $this
     */
    public function pushContext(array $newContext)
    {
        $origin = config('pckg.hook.origin');
        $context = $this->context;
        $data = ['origin' => $origin] + $newContext;
        $data['signature'] = sha1(sha1(json_encode($data)) . json_encode($data));
        $context[] = $data;
        $this->setAndSave([
            'context' => $context,
        ]);

        return $this;
    }

    public function pushProcedures(array $procedures)
    {
        foreach ($procedures as $procedure) {
            $this->procedure[] = $procedure;
        }
        $this->save();

        return $this;
    }

    public function pushProcedure(array $procedure)
    {
        return $this->pushProcedures([$procedure]);
    }

    public function runProcedure()
    {
        $this->make(fn(Task $task) => $task->notification($this->procedure[0]['hook'], $this->procedure[0]['body'] ?? []));

        return $this;
    }

    /**
     * @param callable $make
     *
     * @throws Throwable
     */
    public function make(callable $make, callable $exception = null)
    {
        $this->prepare($make);

        return $this->execute($exception);
    }

    public function queue(string $channel, string $command, array $data = [])
    {
        /*
        $this->setAndSave(['status' => 'queued']);

        queue()->queue('task:queue', [
            '--task'    => $this->id,
            '--channel' => $channel,
            '--command' => $command,
            '--data'    => $data,
        ]);*/
    }

    public function prepare(callable $make)
    {
        $this->make = $make;

        return $this;
    }

    public function toResponse()
    {
        return [
            'success' => $this->status !== 'error',
            'async' => $this->status === 'async',
            'task' => $this->id,
        ];
    }

    public function execute(callable $exception = null)
    {
        try {
            /**
             * Try to execute task.
             */
            $make = $this->make;
            if (!$make) {
                throw new \Exception('Task body should be defined');
            }
            $this->setAndSave([
                'status' => $this->timeouts_at ? 'async' : 'started',
                'started_at' => date('Y-m-d H:i:s'),
            ]);
            $this->result = $make($this);
            if (!$this->timeouts_at) {
                $this->set(['status' => 'ended']);
            } else {
                $this->end();
            }
            return $this->result;
        } catch (Throwable $e) {
            /**
             * If any exception is thrown, mark task as failed.
             */
            $this->set(['status' => 'error']);
            $this->end();
            if ($exception) {
                return $exception($this, $e);
            }

            throw $e;
        }
    }

    public function end(string $event = null, array $payload = [])
    {
        $toUpdate = [
            'ended_at' => date('Y-m-d H:i:s'),
        ];
        if ($event) {
            $toUpdate['status'] = 'ended';
        }
        $this->setAndSave($toUpdate);
        context()->bind(Task::class, $this->parent);

        if (!$event) {
            return;
        }

        Webhook::notification($this, $event, $payload);
    }

    public function async(string $timeout)
    {
        return $this->setAndSave([
            'timeouts_at' => date('Y-m-d H:i:s', strtotime('+' . $timeout)),
        ]);
    }

    public function props(array $props)
    {
        return $this->setAndSave([
            'props' => $props,
        ]);
    }

    public function acquireLock()
    {
        $active = (new Tasks())->where('status', ['started', 'created', 'async'])
            ->where('timeouts_at', date('Y-m-d H:i:s'), '>=')
            ->where('id', $this->id, '!=')
            ->one();

        if (!$active) {
            return $this;
        }

        $this->setAndSave(['status' => 'double']);
        return false;
    }

    public function wrapSubtask(callable $task)
    {
        context()->bind(Task::class, $this);
        $response = $task();
        context()->bind(Task::class, $this->parent);

        return $response;
    }

    public function getLastParentAttribute()
    {
        if (!$this->parent_id) {
            return $this;
        }

        return $this->parent->lastParent;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function notification(string $event, array $body)
    {
        return Webhook::notification($this, $event, $body);
    }

    public function processProcedure(HookEvent $event, $nextTask)
    {
        if (isset($nextTask['complete'])) {
            return;
        }

        if (isset($nextTask['event'])) {
            error_log('Running event ' . $nextTask['command']);
            (new $nextTask['event']($event))->handle();
            return;
        }

        if (!isset($nextTask['hook'])) {
            error_log('Nothing to execute' . json_encode($nextTask));
            return;
        }

        // trigger next task
        // queue('hook-notifications', ['task' => $task->id, 'hook' => $nextTask['hook'], 'body' => $nextTask['body']]);
        $hook = $nextTask['hook'];
        if (is_string($hook)) {
            Webhook::notification($this, $hook, $nextTask['body'] ?? []);
            return;
        }

        foreach ($hook as $origin => $ev) {
            Webhook::notification($this, $ev, $nextTask['body'] ?? [], [$origin]);
        }
    }

    public function getNextTasks(HookEvent $event): Collection
    {
        $fullEvent = $event->getFullEventName();

        return collect($this->procedure->toArray())
            ->filter(fn($subtask) => $fullEvent === $subtask['when']);
    }
}
