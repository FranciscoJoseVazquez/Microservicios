<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Configuración de conexión
$connection = new AMQPStreamConnection('rabbitmq', 5672, 'ATMadmin', 'ATMadmin_1243');
$channel = $connection->channel();

// Declarar la cola
$channel->queue_declare('correos', false, true, false, false);

for ($i = 1; $i <= 50; $i++) {
    $mensajeTexto = "Mensaje número $i";
    $msg = new AMQPMessage($mensajeTexto, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
    $channel->basic_publish($msg, '', 'correos');
    echo "Enviado: $mensajeTexto\n";
}

// Cerrar conexión
$channel->close();
$connection->close();
