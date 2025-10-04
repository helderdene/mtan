<?php

namespace App\Services\MQTT;

use App\DTOs\AttendanceEventDTO;
use App\Jobs\ProcessAttendanceEvent;
use Illuminate\Support\Facades\Log;

class MessageHandler
{
    protected int $totalProcessed = 0;

    protected int $totalErrors = 0;

    protected ?int $lastProcessedAt = null;

    /**
     * Handle incoming MQTT message
     */
    public function handle(string $topic, string $payload): void
    {
        try {
            // Decode payload to inspect structure
            $decodedPayload = json_decode($payload, true);

            Log::channel('mqtt')->info('MQTT message received', [
                'topic' => $topic,
                'payload_length' => strlen($payload),
                'payload_keys' => $decodedPayload ? array_keys($decodedPayload) : null,
                'payload_sample' => $decodedPayload ? array_slice($decodedPayload, 0, 5) : null,
            ]);

            // Route message based on topic pattern
            if (preg_match('/\/Rec$/', $topic)) {
                // Attendance recognition event
                $this->handleAttendanceEvent($topic, $payload);
            } elseif (preg_match('/\/Ack$/', $topic)) {
                // Device sync acknowledgment
                $this->handleSyncAcknowledgment($topic, $payload);
            } elseif ($topic === 'mqtt/face/heartbeat') {
                // Device heartbeat
                $this->handleHeartbeat($topic, $payload);
            } elseif ($topic === 'mqtt/face/basic') {
                // Device basic messages (online, offline, etc.)
                $this->handleBasicMessage($topic, $payload);
            } elseif (preg_match('/^mqtt\/face\/\d+$/', $topic)) {
                // Device-specific messages - mqtt/face/{deviceId}
                $this->handleDeviceMessage($topic, $payload);
            } else {
                // Unknown topic
                Log::channel('mqtt')->warning('Unknown MQTT topic pattern', [
                    'topic' => $topic,
                ]);
            }

            // Update statistics
            $this->totalProcessed++;
            $this->lastProcessedAt = time();

        } catch (\Exception $e) {
            $this->totalErrors++;

            Log::channel('mqtt')->error('Failed to process MQTT message', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle attendance recognition event
     */
    protected function handleAttendanceEvent(string $topic, string $payload): void
    {
        // Parse the MQTT payload into DTO
        $event = AttendanceEventDTO::fromMqttPayload($topic, $payload);

        // Dispatch to queue for processing
        ProcessAttendanceEvent::dispatch($event);

        Log::channel('mqtt')->info('Attendance event dispatched to queue', [
            'device_id' => $event->device_id,
            'event_type' => $event->event_type,
            'person_id' => $event->person_id,
        ]);
    }

    /**
     * Handle device sync acknowledgment
     */
    protected function handleSyncAcknowledgment(string $topic, string $payload): void
    {
        $data = json_decode($payload, true);

        if (! $data) {
            Log::channel('mqtt')->error('Invalid sync acknowledgment payload', [
                'topic' => $topic,
                'payload' => $payload,
            ]);

            return;
        }

        // Extract device_id from topic (e.g., mqtt/face/device001/Ack)
        preg_match('/mqtt\/face\/([^\/]+)\/Ack/', $topic, $matches);
        $deviceId = $matches[1] ?? null;

        if (! $deviceId) {
            Log::channel('mqtt')->error('Unable to parse device sync acknowledgment topic', [
                'topic' => $topic,
            ]);

            return;
        }

        // Extract operator and response data from payload
        $operator = $data['operator'] ?? null;
        $code = $data['code'] ?? null;
        $info = $data['info'] ?? [];

        if (! $operator) {
            Log::channel('mqtt')->error('Missing operator in acknowledgment payload', [
                'topic' => $topic,
                'payload' => $data,
            ]);

            return;
        }

        // Determine if operation was successful based on code
        $success = ($code === '200' || $code === 200);

        // Extract base operator name (remove -Ack suffix)
        $baseOperator = str_replace('-Ack', '', $operator);

        // Add success flag to info for job processing
        $info['success'] = $success;
        $info['result'] = $info['result'] ?? ($success ? 'ok' : 'failed');

        // Dispatch job to update enrollment status
        \App\Jobs\UpdateDeviceEnrollmentStatus::dispatch($deviceId, $info, $baseOperator);

        Log::channel('mqtt')->info('Device sync acknowledgment received', [
            'device_id' => $deviceId,
            'operator' => $operator,
            'base_operator' => $baseOperator,
            'code' => $code,
            'success' => $success,
            'customId' => $info['customId'] ?? null,
            'result' => $info['result'] ?? null,
        ]);
    }

    /**
     * Handle device heartbeat
     */
    protected function handleHeartbeat(string $topic, string $payload): void
    {
        $data = json_decode($payload, true);

        if (! $data) {
            Log::channel('mqtt')->error('Invalid heartbeat payload', [
                'topic' => $topic,
                'payload' => $payload,
            ]);

            return;
        }

        // Extract operator and info from payload
        $operator = $data['operator'] ?? null;
        $info = $data['info'] ?? [];

        if ($operator !== 'HeartBeat') {
            Log::channel('mqtt')->warning('Unexpected heartbeat operator', [
                'topic' => $topic,
                'operator' => $operator,
            ]);
        }

        // Extract device ID from facesluiceId
        $facesluiceId = $info['facesluiceId'] ?? null;

        if (! $facesluiceId) {
            Log::channel('mqtt')->error('Missing facesluiceId in heartbeat payload', [
                'topic' => $topic,
                'payload' => $data,
            ]);

            return;
        }

        // Dispatch job to update heartbeat timestamp
        \App\Jobs\UpdateDeviceHeartbeat::dispatch($facesluiceId, $info);

        Log::channel('mqtt')->debug('Device heartbeat received', [
            'facesluiceId' => $facesluiceId,
            'time' => $info['time'] ?? null,
        ]);
    }

    /**
     * Handle basic device messages (online, offline, etc.)
     */
    protected function handleBasicMessage(string $topic, string $payload): void
    {
        $data = json_decode($payload, true);

        if (! $data) {
            Log::channel('mqtt')->error('Invalid basic message payload', [
                'topic' => $topic,
                'payload' => $payload,
            ]);

            return;
        }

        // Extract operator and info from payload
        $operator = $data['operator'] ?? null;
        $info = $data['info'] ?? [];

        if (! $operator) {
            Log::channel('mqtt')->error('Missing operator in basic message payload', [
                'topic' => $topic,
                'payload' => $data,
            ]);

            return;
        }

        // Extract device ID from facesluiceId
        $facesluiceId = $info['facesluiceId'] ?? null;

        if (! $facesluiceId) {
            Log::channel('mqtt')->error('Missing facesluiceId in basic message payload', [
                'topic' => $topic,
                'payload' => $data,
            ]);

            return;
        }

        // Handle different operators
        if ($operator === 'Online') {
            // Dispatch job to handle device online status
            \App\Jobs\HandleDeviceOnlineStatus::dispatch($facesluiceId, $info);
        } elseif ($operator === 'Online-Ack') {
            // Ignore our own acknowledgments - do not reply to avoid infinite loop
            Log::channel('mqtt')->debug('Online-Ack received (ignoring)', [
                'facesluiceId' => $facesluiceId,
            ]);
        } else {
            Log::channel('mqtt')->info('Basic message received', [
                'operator' => $operator,
                'facesluiceId' => $facesluiceId,
                'info' => $info,
            ]);
        }
    }

    /**
     * Handle device-specific messages
     */
    protected function handleDeviceMessage(string $topic, string $payload): void
    {
        $data = json_decode($payload, true);

        if (! $data) {
            Log::channel('mqtt')->error('Invalid device message payload', [
                'topic' => $topic,
                'payload' => $payload,
            ]);

            return;
        }

        // Extract device ID from topic (e.g., mqtt/face/2582493)
        preg_match('/^mqtt\/face\/(\d+)$/', $topic, $matches);
        $facesluiceId = $matches[1] ?? null;

        if (! $facesluiceId) {
            Log::channel('mqtt')->error('Unable to parse device ID from topic', [
                'topic' => $topic,
            ]);

            return;
        }

        // Extract operator and info from payload
        $operator = $data['operator'] ?? null;
        $info = $data['info'] ?? [];

        Log::channel('mqtt')->info('Device-specific message received', [
            'topic' => $topic,
            'facesluiceId' => $facesluiceId,
            'operator' => $operator,
            'info' => $info,
        ]);
    }

    /**
     * Get processing statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_processed' => $this->totalProcessed,
            'total_errors' => $this->totalErrors,
            'last_processed_at' => $this->lastProcessedAt,
        ];
    }
}
