<?php namespace Pckg\Task\Record;

use Pckg\Database\Entity;
use Pckg\Database\Field\JsonArray;
use Pckg\Database\Field\JsonObject;
use Pckg\Database\Record;
use Pckg\Task\Entity\Tasks;
use Pckg\Task\Form\Hook;
use Pckg\Task\Service\Webhook;
use Throwable;

class Task extends Record
{

    protected $entity = Tasks::class;

    /**
     * @var callable
     */
    protected $make;

    /**
     * @var null|mixed
     */
    protected $result = null;

    protected $encapsulate = [
        'props' => JsonObject::class,
        'context' => JsonArray::class,
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

    public static function named(string $name): Task
    {
        return static::create([
            'title' => $name,
            'context' => [],
        ]);
    }

    /**
     * @param $data
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
            'origin' => config('pckg.task.origin'),
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
        $origin = config('pckg.task.origin');
        $context = $this->context;
        $context[] = ['origin' => $origin] + $newContext;
        $this->setAndSave([
            'context' => $context,
        ]);

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
                $this->set(['status' => $this->timeouts_at ? 'async' : 'ended']);
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
}
