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
        $this->addTextarea('body')->required(); // json
        $this->addTextarea('context'); // json
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
            json_decode($data['body'], true),
            json_decode($data['context'], true),
            (int)$data['retry']
        );
    }
}
