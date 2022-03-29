<?php

namespace Pckg\Task\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Pckg\Api\Record\ApiLog;
use Pckg\Task\Record\Task;

class Webhook
{
    public static function notification(?Task $task, string $event, $body, array $onlyOrigins = [])
    {
        $lastParent = $task->lastParent;
        $payload = [
            // object?
            'body' => is_only_callable($body) ? $body($task, $event) : $body,
            // array? sign the context?
            'context' => $lastParent->context,
            'task' => $lastParent->id,
        ];

        if ($lastParent->id !== $task->id) {
            $payload['subtask'] = $task->id;
        }

        return static::processNotification($payload, $event, $onlyOrigins);
    }

    public static function processNotification(array $data, string $event, array $onlyOrigins = [])
    {
        $data['origin'] = config('pckg.hook.origin');
        $data['retry'] = 0;
        $data['event'] = explode('@', $event)[0];
        
        $origins = config('pckg.hook.origins', []);
        foreach ($origins as $key => $origin) {
            $partialEvent = explode('@', $event)[0];
            if ($partialEvent !== $event && $event !== ($partialEvent . '@' . $key)) {
                continue;
            }
            if ($onlyOrigins && !in_array($key, $onlyOrigins)) {
                continue;
            }
            if ($partialEvent === $event) {
                // origin is listening for events
                $events = $origin['listeners'] ?? [];
                if (!$events || !in_array('*', $events) && !in_array($event, $events)) {
                    continue;
                }
            }

            // this should be queued?
            try {
                ApiLog::create([
                    'type' => 'external:request:POST',
                    'created_at' => date('Y-m-d H:i:s'),
                    'data' => json_encode($data),
                    'ip' => 'localhost',
                    'url' => $origin['url'],
                ]);

                $response = null;
                try {
                    // can we log this in api_logs?
                    $response = (new Client([]))->post($origin['url'], [
                        RequestOptions::JSON => $data,
                        RequestOptions::TIMEOUT => 5,
                    ]);
                } catch (\Throwable $e) {
                    trigger(Webhook::class . '.notificationException', [
                        'url' => $origin['url'],
                        'payload' => $data,
                    ]);
                } finally {
                    ApiLog::create([
                        'type' => 'external:response:' . ($response ? $response->getStatusCode() : 'exception'),
                        'created_at' => date('Y-m-d H:i:s'),
                        'data' => $response ? $response->getBody()->getContents() : null,
                        'ip' => 'localhost',
                        'url' => $origin['url'],
                    ]);
                }
            } catch (\Throwable $e) {
                error_log(exception($e));
            }
        }
    }
}
