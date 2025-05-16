<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Configuración de conexión
$connection = new AMQPStreamConnection('rabbitmq', 5672, 'ATMadmin', 'ATMadmin_1243');
$channel = $connection->channel();

// Declarar las colas
$colas = ['whatsapp', 'cola_telegram', 'cola_sms', 'logs'];
foreach ($colas as $cola) {
    $channel->queue_declare($cola, false, true, false, false);
}

// Enviar mensajes a cada cola
for ($i = 1; $i <= 100000; $i++) {
    $mensajeTexto = "Mensaje número $i";
    foreach ($colas as $cola) {
        $msg = new AMQPMessage($mensajeTexto, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $channel->basic_publish($msg, '', $cola);
        echo "Enviado a $cola: $mensajeTexto\n";
    }
}

// Cerrar conexión
$channel->close();
$connection->close();
