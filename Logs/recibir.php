<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

// Intentar conectar a RabbitMQ con reintentos
$max_retries = 10;
$retry_delay = 3;
$attempt = 0;

do {
    try {
        $connection = new AMQPStreamConnection('rabbitmq', 5672, 'ATMadmin', 'ATMadmin_1243');
        $channel = $connection->channel();
        break;
    } catch (Exception $e) {
        echo "[!] Error conectando a RabbitMQ: {$e->getMessage()}\n";
        $attempt++;
        if ($attempt >= $max_retries) {
            echo "[x] No se pudo conectar a RabbitMQ después de $attempt intentos. Abortando.\n";
            exit(1);
        }
        sleep($retry_delay);
    }
} while (true);

// Declarar la cola 'logs'
$channel->queue_declare('logs', false, true, false, false);

echo " [*] Esperando mensajes en la cola 'logs'. Para salir presiona CTRL+C\n";

$callback = function ($msg) {
    $timestamp = date('Y-m-d H:i:s');
    $logData = json_decode($msg->body, true);

    if (!$logData) {
        echo " [x] Mensaje no válido. Se ignora.\n";
        return;
    }

    $entry = "[$timestamp] " . json_encode($logData, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents(__DIR__ . '/info.log', $entry, FILE_APPEND);

    echo " [✔] Log escrito: $entry";
};

// Configurar QoS y consumidor
$channel->basic_qos(null, 1, null);
$channel->basic_consume('logs', '', false, true, false, false, $callback);

// Bucle de espera
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
