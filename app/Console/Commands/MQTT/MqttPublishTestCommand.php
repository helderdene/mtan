<?php

namespace App\Console\Commands\MQTT;

use App\Services\MQTT\MQTTClient;
use Illuminate\Console\Command;

class MqttPublishTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:publish-test
                            {--device=DEVICE001 : Device ID to use}
                            {--employee=EMP001 : Employee custom ID}
                            {--type=recognition : Event type (recognition or stranger)}
                            {--similarity=0.9500 : Recognition similarity score}
                            {--count=1 : Number of messages to publish}
                            {--delay=0 : Delay in seconds between messages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish test MQTT messages to simulate biometric device events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deviceId = $this->option('device');
        $employeeId = $this->option('employee');
        $eventType = $this->option('type');
        $similarity = (float) $this->option('similarity');
        $count = (int) $this->option('count');
        $delay = (int) $this->option('delay');

        $this->info("Publishing {$count} test MQTT message(s) for device: {$deviceId}");
        $this->newLine();

        try {
            $client = new MQTTClient();

            for ($i = 1; $i <= $count; $i++) {
                $topic = $this->buildTopic($deviceId, $eventType);
                $payload = $this->buildPayload($deviceId, $employeeId, $eventType, $similarity);

                $this->line("[$i/$count] Publishing to topic: {$topic}");
                $this->line("Payload: " . json_encode(json_decode($payload), JSON_PRETTY_PRINT));

                $client->publish($topic, $payload);

                $this->info("✓ Message published successfully");
                $this->newLine();

                if ($i < $count && $delay > 0) {
                    $this->line("Waiting {$delay} seconds...");
                    sleep($delay);
                    $this->newLine();
                }
            }

            $this->info("✓ All {$count} message(s) published successfully!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to publish MQTT message: " . $e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * Build the MQTT topic for the event
     */
    protected function buildTopic(string $deviceId, string $eventType): string
    {
        return "device/{$deviceId}/{$eventType}";
    }

    /**
     * Build the MQTT payload for the event
     */
    protected function buildPayload(
        string $deviceId,
        ?string $employeeId,
        string $eventType,
        float $similarity
    ): string {
        $timestamp = now()->format('Y-m-d H:i:s');

        if ($eventType === 'stranger') {
            return json_encode([
                'device_id' => $deviceId,
                'timestamp' => $timestamp,
                'image_url' => '/storage/strangers/' . now()->format('Ymd_His') . '.jpg',
            ]);
        }

        // Recognition event
        return json_encode([
            'device_id' => $deviceId,
            'person_id' => $employeeId,
            'timestamp' => $timestamp,
            'temperature' => round(35.5 + (rand(0, 20) / 10), 1), // 35.5 - 37.5°C
            'mask' => (bool) rand(0, 1),
            'similarity' => $similarity,
        ]);
    }
}
