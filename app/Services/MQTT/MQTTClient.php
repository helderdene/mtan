<?php

namespace App\Services\MQTT;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MQTTClient
{
    protected ?string $clientId;
    protected array $config;
    protected bool $connected = false;
    protected ?int $lastActivity = null;
    protected $messageCallback = null;

    public function __construct()
    {
        $this->validateConfig();
        $this->clientId = config('mqtt.client_id') ?? 'laravel_' . Str::random(8);
        $this->config = $this->buildConnectionConfig();
    }

    /**
     * Validate required MQTT configuration
     */
    protected function validateConfig(): void
    {
        $required = ['host', 'port'];

        foreach ($required as $key) {
            if (empty(config("mqtt.{$key}"))) {
                throw new \InvalidArgumentException("MQTT configuration missing: {$key}");
            }
        }
    }

    /**
     * Build connection configuration
     */
    protected function buildConnectionConfig(): array
    {
        $config = [
            'host' => config('mqtt.host'),
            'port' => config('mqtt.port', 1883),
            'username' => config('mqtt.username'),
            'password' => config('mqtt.password'),
            'keep_alive' => config('mqtt.keep_alive', 60),
            'clean_session' => config('mqtt.clean_session', true),
        ];

        // Add TLS configuration if enabled
        if (config('mqtt.tls.enabled', false)) {
            $config['tls'] = true;
            $config['tls_config'] = [
                'ca_file' => config('mqtt.tls.ca_file'),
                'client_cert' => config('mqtt.tls.client_cert'),
                'client_key' => config('mqtt.tls.client_key'),
                'verify_peer' => config('mqtt.tls.verify_peer', true),
                'verify_peer_name' => config('mqtt.tls.verify_peer_name', true),
            ];
        }

        return $config;
    }

    /**
     * Get client ID
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Get connection configuration
     */
    public function getConnectionConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if client is connected
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get subscribed topics
     */
    public function getSubscribedTopics(): array
    {
        return array_values(config('mqtt.topics', []));
    }

    /**
     * Log connection attempt
     */
    public function logConnectionAttempt(): void
    {
        Log::channel('mqtt')->info('Attempting MQTT connection', [
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'client_id' => $this->clientId,
        ]);
    }

    /**
     * Log connection success
     */
    public function logConnectionSuccess(): void
    {
        $this->connected = true;
        $this->lastActivity = time();

        Log::channel('mqtt')->info('MQTT connection established', [
            'client_id' => $this->clientId,
            'topics' => $this->getSubscribedTopics(),
        ]);
    }

    /**
     * Log connection failure
     */
    public function logConnectionFailure(\Exception $exception): void
    {
        $this->connected = false;

        Log::channel('mqtt')->error('MQTT connection failed', [
            'client_id' => $this->clientId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get health status
     */
    public function getHealthStatus(): array
    {
        return [
            'connected' => $this->connected,
            'last_activity' => $this->lastActivity,
            'client_id' => $this->clientId,
            'topics' => $this->getSubscribedTopics(),
            'host' => $this->config['host'],
            'port' => $this->config['port'],
        ];
    }

    /**
     * Set message callback
     */
    public function setMessageCallback($callback): void
    {
        if (! is_callable($callback)) {
            throw new \InvalidArgumentException('Message callback must be callable');
        }

        $this->messageCallback = $callback;
    }

    /**
     * Check if message callback is set
     */
    public function hasMessageCallback(): bool
    {
        return $this->messageCallback !== null;
    }

    /**
     * Update last activity timestamp
     */
    protected function updateActivity(): void
    {
        $this->lastActivity = time();
    }

    /**
     * Publish message to MQTT topic
     */
    public function publish(string $topic, string $payload, int $qos = 0, bool $retain = false): void
    {
        try {
            // Create a new MQTT client for publishing
            $clientId = config('mqtt.client_id') ?? 'laravel_publisher_' . uniqid();

            $mqtt = new \PhpMqtt\Client\MqttClient(
                config('mqtt.host'),
                config('mqtt.port'),
                $clientId
            );

            // Configure connection settings
            $connectionSettings = (new \PhpMqtt\Client\ConnectionSettings())
                ->setKeepAliveInterval(config('mqtt.keep_alive', 60))
                ->setUsername(config('mqtt.username'))
                ->setPassword(config('mqtt.password'));

            // Connect to broker
            $mqtt->connect($connectionSettings, true);

            // Publish message
            $mqtt->publish($topic, $payload, $qos, $retain);

            // Disconnect
            $mqtt->disconnect();

            Log::channel('mqtt')->info('Publishing MQTT message', [
                'topic' => $topic,
                'payload_length' => strlen($payload),
                'qos' => $qos,
                'retain' => $retain,
                'payload_preview' => substr($payload, 0, 500), // Log first 500 chars for debugging
            ]);

            $this->updateActivity();
        } catch (\Exception $e) {
            Log::channel('mqtt')->error('Failed to publish MQTT message', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
