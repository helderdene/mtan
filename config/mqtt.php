<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MQTT Broker Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the MQTT broker connection used to receive attendance
    | events from biometric devices.
    |
    */

    'host' => env('MQTT_HOST', '148.230.99.73'),
    'port' => env('MQTT_PORT', 1883),

    /*
    |--------------------------------------------------------------------------
    | MQTT Authentication
    |--------------------------------------------------------------------------
    */

    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Client Configuration
    |--------------------------------------------------------------------------
    */

    'client_id' => env('MQTT_CLIENT_ID', null), // Will auto-generate if null
    'keep_alive' => env('MQTT_KEEP_ALIVE', 60),
    'clean_session' => env('MQTT_CLEAN_SESSION', true),

    /*
    |--------------------------------------------------------------------------
    | Topic Subscriptions
    |--------------------------------------------------------------------------
    |
    | Topics to subscribe to. Uses MQTT wildcards (+) for device IDs.
    |
    */

    'topics' => [
        'recognition' => env('MQTT_TOPIC_RECOGNITION', 'mqtt/face/+/Rec'),
        'stranger' => env('MQTT_TOPIC_STRANGER', 'mqtt/face/+/Stranger'),
        'ack' => env('MQTT_TOPIC_ACK', 'mqtt/face/+/Ack'),
        'heartbeat' => env('MQTT_TOPIC_HEARTBEAT', 'mqtt/face/heartbeat'),
        'basic' => env('MQTT_TOPIC_BASIC', 'mqtt/face/basic'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TLS/SSL Configuration
    |--------------------------------------------------------------------------
    |
    | Enable TLS for secure MQTT connections. Requires certificates.
    |
    */

    'tls' => [
        'enabled' => env('MQTT_TLS_ENABLED', false),
        'ca_file' => env('MQTT_TLS_CA_FILE'),
        'client_cert' => env('MQTT_TLS_CLIENT_CERT'),
        'client_key' => env('MQTT_TLS_CLIENT_KEY'),
        'verify_peer' => env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name' => env('MQTT_TLS_VERIFY_PEER_NAME', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Behavior
    |--------------------------------------------------------------------------
    */

    'reconnect_timeout' => env('MQTT_RECONNECT_TIMEOUT', 5), // seconds
    'max_reconnect_attempts' => env('MQTT_MAX_RECONNECT_ATTEMPTS', null), // null = infinite
    'connection_timeout' => env('MQTT_CONNECTION_TIMEOUT', 60), // seconds

    /*
    |--------------------------------------------------------------------------
    | Quality of Service (QoS)
    |--------------------------------------------------------------------------
    |
    | 0 = At most once (fire and forget)
    | 1 = At least once (acknowledged delivery)
    | 2 = Exactly once (assured delivery)
    |
    */

    'qos' => env('MQTT_QOS', 1),
];
