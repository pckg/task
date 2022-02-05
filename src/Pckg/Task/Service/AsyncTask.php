<?php

namespace Pckg\Task\Service;

use Pckg\Task\Record\Task;

trait AsyncTask
{
    public function asyncTask(string $name, string $duration, callable $callable, bool $lock = false)
    {
        /**
         * Do we want to acquire lock?
         */
        $task = Task::create($name);

        if ($lock && !$task->acquireLock()) {
            response()->bad('Task locked, retry in next ' . $duration);
        }

        try {
            $task->async($duration)
                ->make($callable);

            return [
                'success' => true,
                'async' => true,
                'task' => $task->transform(['id']),
            ];
        } catch (\Throwable $e) {
            error_log(exception($e));

            response()->fatal('Something went wrong. Please, retry or contact us.');
        }
    }
}
