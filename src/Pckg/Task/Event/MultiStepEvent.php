<?php

namespace Pckg\Task\Event;

use Pckg\Task\Record\Task;
use Pckg\Task\Service\AsyncTask;
use Pckg\Task\Service\Procedure;
use Pckg\Task\Service\Webhook;

abstract class MultiStepEvent extends AbstractHookEvent implements Procedure
{
    use AsyncTask;

    protected string $name;

    protected string $duration = '30minutes';

    public function make()
    {
        return $this->asyncTask(
            $this->name,
            $this->duration,
            fn(Task $task) => $this->start($task),
        );
    }

    public function start(Task $task)
    {
        $procedure = $this->getProcedure();

        // save procedure
        $task->setAndSave([
            'procedure' => $procedure,
        ]);

        if (!isset($procedure[0]['hook'])) {
            return;
        }

        // start with first command
        Webhook::notification($task, $procedure[0]['hook'], $procedure[0]['body'] ?? []);
    }
}
