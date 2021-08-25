<?php namespace Pckg\Task\Record;

use Pckg\Database\Entity;
use Pckg\Database\Record;
use Pckg\Task\Entity\Tasks;
use Throwable;

class Task extends Record
{

    protected $entity = Tasks::class;

    /**
     * @var callable
     */
    protected $make;

    public static function create($data = [], Entity $entity = null)
    {
        /**
         * Get current task.
         */
        $parentTask = context()->getOrDefault(Task::class);

        /**
         * If title was passed, transform it.
         */
        if (is_string($data)) {
            if (isConsole()) {
                d($data);
            }
            $data = ['title' => $data];
        }

        /**
         * Create task in database.
         */
        $task = parent::create(array_merge($data, [
            'parent_id' => $parentTask->id ?? null,
            'status' => 'created',
        ]), $entity);

        /**
         * Bind it as current.
         */
        context()->bind(Task::class, $task);

        return $task;
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
            $result = $make();
            if (!$this->timeouts_at) {
                $this->set(['status' => $this->timeouts_at ? 'async' : 'ended']);
            }
            $this->end();

            return $result;
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

    public function end()
    {
        $this->setAndSave(['ended_at' => date('Y-m-d H:i:s')]);
        context()->bind(Task::class, $this->parent);
    }

    public function async(string $timeout)
    {
        return $this->setAndSave([
            'timeouts_at' => date('Y-m-d H:i:s', strtotime('+' . $timeout)),
        ]);
    }

    public function acquireLock()
    {
        $active = (new Tasks())->where('status', ['started', 'created', 'async'])
            ->where('started_at', date('Y-m-d H:i:s', '-1hour'), '>=')
            ->where('id', $this->id, '!=')
            ->one();

        if (!$active) {
            return true;
        }

        $this->setAndSave(['status' => 'double']);
        return false;
    }
}
