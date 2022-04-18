<?php

namespace Pckg\Task\Form;

use Pckg\Htmlbuilder\Element\Form;
use Pckg\Task\Event\HookEvent;

class Hook extends Form implements Form\ResolvesOnRequest
{
    public function initFields()
    {
        $this->addText('origin')->required();
        $this->addText('event')->required();
        $this->addText('body')->required(); // json/object
        $this->addText('context'); // json/array
        $this->addInteger('retry');

        //$this->addTextarea('task'); // json
        //$this->addTextarea('subtask'); // json

        return $this;
    }

    public function toHookEvent(): HookEvent
    {
        $data = $this->getData();

        return new HookEvent(
            $data['origin'],
            $data['event'],
            $data['body'],
            $data['context'],
            (int)$data['retry']
        );
    }
}
