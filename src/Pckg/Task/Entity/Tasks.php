<?php

namespace Pckg\Task\Entity;

use Pckg\Database\Entity;
use Pckg\Task\Record\Task;

class Tasks extends Entity
{
    protected $record = Task::class;

    public function parent()
    {
        return $this->belongsTo(static::class)->foreignKey('parent_id');
    }
}
