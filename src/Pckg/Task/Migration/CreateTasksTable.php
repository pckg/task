<?php

namespace Pckg\Task\Migration;

use Pckg\Migration\Migration;

class CreateTasksTable extends Migration
{
    public function up()
    {
        $tasks = $this->table('tasks');
        $tasks->title();
        $tasks->parent();
        $tasks->varchar('status');
        $tasks->datetime('started_at');
        $tasks->datetime('ended_at');
        $tasks->longtext('data');

        $this->save();
    }
}
