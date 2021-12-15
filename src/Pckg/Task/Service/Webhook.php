<?php

namespace Pckg\Task\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Pckg\Api\Record\ApiLog;
use Pckg\Task\Record\Task;

class Webhook
{
    public static function buildPayload(Task $task, string $event, array $body): array
    {
        $lastParent = $task->lastParent;
        $payload = [
            'origin' => config('pckg.hook.origin'),
            'event' => $event,
            'body' => $body,
            'context' => $lastParent->context,
            'retry' => 0,
            'task' => $lastParent->id,
        ];

        if ($lastParent->id !== $task->id) {
            $payload['subtask'] = $task->id;
        }

        return $payload;
    }

    public static function notification(Task $task, string $event, array $payload)
    {
        $data = static::buildPayload($task, $event, $payload);
        $origins = config('pckg.hook.origins', []);
        foreach ($origins as $origin) {
            // origin is listening for events
            $events = $origin['listeners'] ?? [];
            if (!$events || !in_array('*', $events) && !in_array($event, $events)) {
                continue;
            }

            try {
                ApiLog::create([
                    'type' => 'external:request:POST',
                    'created_at' => date('Y-m-d H:i:s'),
                    'data' => json_encode($data),
                    'ip' => 'localhost',
                    'url' => $url,
                ]);

                $response = null;
                try {
                    // can we log this in api_logs?
                    $response = (new Client([]))->post($urls, [
                        RequestOptions::JSON => $data,
                        RequestOptions::TIMEOUT => 5,
                    ]);
                } catch (\Throwable $e) {
                    trigger(Webhook::class . '.notificationException', [
                        'url' => $url,
                        'payload' => $data,
                    ]);
                } finally {
                    ApiLog::create([
                        'type' => 'external:response:' . ($response ? $response->getStatusCode() : 'exception'),
                        'created_at' => date('Y-m-d H:i:s'),
                        'data' => $response ? $response->getBody() : null,
                        'ip' => 'localhost',
                        'url' => $url,
                    ]);
                }
            } catch (\Throwable $e) {
                error_log(exception($e));
            }
        }
    }
}
