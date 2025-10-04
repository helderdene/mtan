<?php

use App\Services\MQTT\MQTTClient;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Mock MQTT broker configuration
    config([
        'mqtt.host' => 'mqtt.test.com',
        'mqtt.port' => 1883,
        'mqtt.client_id' => 'test-client',
        'mqtt.username' => 'test_user',
        'mqtt.password' => 'test_password',
        'mqtt.topics' => [
            'recognition' => 'device/+/recognition',
            'stranger' => 'device/+/stranger',
            'ack' => 'device/+/ack',
        ],
        'mqtt.tls' => [
            'enabled' => false,
        ],
        'mqtt.keep_alive' => 60,
        'mqtt.reconnect_timeout' => 5,
    ]);
});

describe('MQTT Client', function () {
    test('can instantiate MQTT client', function () {
        $client = new MQTTClient;

        expect($client)->toBeInstanceOf(MQTTClient::class);
    });

    test('generates unique client ID when not provided', function () {
        config(['mqtt.client_id' => null]);

        $client1 = new MQTTClient;
        $client2 = new MQTTClient;

        expect($client1->getClientId())->not->toBeNull();
        expect($client2->getClientId())->not->toBeNull();
        expect($client1->getClientId())->not->toBe($client2->getClientId());
    });

    test('uses configured client ID when provided', function () {
        config(['mqtt.client_id' => 'custom-client-id']);

        $client = new MQTTClient;

        expect($client->getClientId())->toBe('custom-client-id');
    });

    test('can get connection configuration', function () {
        $client = new MQTTClient;
        $config = $client->getConnectionConfig();

        expect($config)->toBeArray();
        expect($config['host'])->toBe('mqtt.test.com');
        expect($config['port'])->toBe(1883);
        expect($config['username'])->toBe('test_user');
    });

    test('connection config includes TLS when enabled', function () {
        config([
            'mqtt.tls.enabled' => true,
            'mqtt.tls.ca_file' => '/path/to/ca.crt',
            'mqtt.tls.client_cert' => '/path/to/client.crt',
            'mqtt.tls.client_key' => '/path/to/client.key',
        ]);

        $client = new MQTTClient;
        $config = $client->getConnectionConfig();

        expect($config['tls'])->toBeTrue();
        expect($config['tls_config'])->toBeArray();
        expect($config['tls_config']['ca_file'])->toBe('/path/to/ca.crt');
    });

    test('validates required configuration on instantiation', function () {
        config(['mqtt.host' => null]);

        expect(fn () => new MQTTClient)->toThrow(\InvalidArgumentException::class);
    });

    test('can check if client is connected', function () {
        $client = new MQTTClient;

        expect($client->isConnected())->toBeFalse();
    });

    test('can get subscribed topics', function () {
        $client = new MQTTClient;

        $topics = $client->getSubscribedTopics();

        expect($topics)->toBeArray();
        expect($topics)->toContain('device/+/recognition');
        expect($topics)->toContain('device/+/stranger');
        expect($topics)->toContain('device/+/ack');
    });

    test('logs connection attempts', function () {
        Log::shouldReceive('channel')
            ->with('mqtt')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->with('Attempting MQTT connection', \Mockery::type('array'))
            ->once();

        $client = new MQTTClient;
        $client->logConnectionAttempt();
    });

    test('logs connection success', function () {
        Log::shouldReceive('channel')
            ->with('mqtt')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->with('MQTT connection established', \Mockery::type('array'))
            ->once();

        $client = new MQTTClient;
        $client->logConnectionSuccess();
    });

    test('logs connection failures', function () {
        Log::shouldReceive('channel')
            ->with('mqtt')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->with('MQTT connection failed', \Mockery::type('array'))
            ->once();

        $client = new MQTTClient;
        $client->logConnectionFailure(new \Exception('Connection timeout'));
    });

    test('can get health status', function () {
        $client = new MQTTClient;

        $health = $client->getHealthStatus();

        expect($health)->toBeArray();
        expect($health)->toHaveKeys(['connected', 'last_activity', 'client_id', 'topics']);
    });

    test('health status shows disconnected when not connected', function () {
        $client = new MQTTClient;

        $health = $client->getHealthStatus();

        expect($health['connected'])->toBeFalse();
        expect($health['last_activity'])->toBeNull();
    });

    test('can set message callback', function () {
        $client = new MQTTClient;

        $callback = function ($topic, $message) {
            return true;
        };

        $client->setMessageCallback($callback);

        expect($client->hasMessageCallback())->toBeTrue();
    });

    test('validates message callback is callable', function () {
        $client = new MQTTClient;

        expect(fn () => $client->setMessageCallback('not-a-function'))->toThrow(\InvalidArgumentException::class);
    });
});
