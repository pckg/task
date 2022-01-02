<?php

namespace Pckg\Task\Service;

use Pckg\Task\Record\Task;

interface Procedure
{
    public function getProcedure(): array;

    public function start(Task $task);
}
