<?php

namespace App\Console\Commands\MQTT;

use App\Services\MQTT\MessageHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;

class MqttConsumeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:consume
                            {--once : Process messages once and exit}
                            {--timeout=0 : Maximum runtime in seconds (0 = unlimited)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume MQTT messages from biometric devices and process attendance events';

    protected MessageHandler $handler;
    protected bool $shouldStop = false;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->handler = new MessageHandler();

        $this->info('Starting MQTT consumer...');
        $this->info('Broker: ' . config('mqtt.host') . ':' . config('mqtt.port'));

        $startTime = time();
        $timeout = (int) $this->option('timeout');

        // Handle graceful shutdown
        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);

        try {
            $clientId = config('mqtt.client_id') ?? 'laravel_consumer_' . uniqid();

            // Create MQTT client
            $mqtt = new MqttClient(
                config('mqtt.host'),
                config('mqtt.port'),
                $clientId
            );

            // Configure connection options
            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings())
                ->setKeepAliveInterval(config('mqtt.keep_alive', 60))
                ->setUsername(config('mqtt.username'))
                ->setPassword(config('mqtt.password'));

            // Connect to broker
            $this->info('Connecting to MQTT broker...');
            $mqtt->connect($connectionSettings, true);
            $this->info('✓ Connected successfully');

            // Subscribe to topics
            $topics = config('mqtt.topics', []);
            foreach ($topics as $name => $pattern) {
                $mqtt->subscribe($pattern, function ($topic, $message) {
                    $this->handleMessage($topic, $message);
                }, config('mqtt.qos', 1));

                $this->info("✓ Subscribed to: {$pattern}");
            }

            $this->newLine();
            $this->info('Listening for messages... (Press Ctrl+C to stop)');
            $this->newLine();

            // Main loop
            while (! $this->shouldStop) {
                pcntl_signal_dispatch();

                // Check timeout
                if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                    $this->warn('Timeout reached');
                    break;
                }

                // Process messages
                $mqtt->loop(true);

                // Exit after one loop if --once flag is set
                if ($this->option('once')) {
                    break;
                }

                // Small sleep to prevent CPU spinning
                usleep(100000); // 0.1 seconds
            }

            // Disconnect
            $mqtt->disconnect();
            $this->info('Disconnected from MQTT broker');

            // Show statistics
            $this->displayStatistics();

            return 0;
        } catch (\Exception $e) {
            Log::channel('mqtt')->error('MQTT consumer error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('MQTT consumer error: ' . $e->getMessage());

            return 1;
        }
    }

    /**
     * Handle incoming MQTT message
     */
    protected function handleMessage(string $topic, string $message): void
    {
        $this->line("→ Message received on topic: {$topic}");

        $this->handler->handle($topic, $message);
    }

    /**
     * Handle shutdown signal
     */
    public function handleShutdown(): void
    {
        $this->newLine();
        $this->warn('Received shutdown signal...');
        $this->shouldStop = true;
    }

    /**
     * Display processing statistics
     */
    protected function displayStatistics(): void
    {
        $stats = $this->handler->getStatistics();

        $this->newLine();
        $this->info('Processing Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Processed', $stats['total_processed']],
                ['Total Errors', $stats['total_errors']],
                ['Last Processed', $stats['last_processed_at'] ? date('Y-m-d H:i:s', $stats['last_processed_at']) : 'N/A'],
            ]
        );
    }
}
