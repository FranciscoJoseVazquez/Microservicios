<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Esperar hasta que RabbitMQ esté disponible
$max_retries = 10;
$retry_delay = 3; // segundos
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

$channel->queue_declare('cola_telegram', false, true, false, false);
$channel->queue_declare('logs', false, true, false, false);

echo " [*] Esperando mensajes en la cola 'telegram'. Para salir presiona CTRL+C\n";

$callback = function ($msg) use ($channel) {
    $timestamp = date('Y-m-d H:i:s');
    $datos = json_decode($msg->body, true);

    if (!$datos || !isset($datos['destinatario'], $datos['asunto'], $datos['cuerpo'], $datos['token'], $datos['id'])) {
        echo " [x] Mensaje incompleto o malformado.\n";
        $channel->basic_nack($msg->delivery_info['delivery_tag'], false, false);
        return;
    }

    // Validar token
    $token = $datos['token'];
    $validador_url = 'http://token/validacion_token.php';

    $ch = curl_init($validador_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        echo " [!] Error al validar token\n";
        $channel->basic_nack($msg->delivery_info['delivery_tag'], false, false);
        return;
    }

    $data = json_decode($response, true);
    if (!$data || !$data['status']) {
        echo " [!] Token inválido o error en validación: " . ($data['message'] ?? 'Sin mensaje') . "\n";
        $channel->basic_nack($msg->delivery_info['delivery_tag'], false, false);
        return;
    }

    // Procesar mensaje
    echo " [✔] Procesando mensaje para: {$datos['destinatario']}\n";

    // Comentado para pruebas: no se publica mensaje en la cola logs
    
    $log_data = [
        'id' => $datos['id'],
        'mensaje' => $datos['mensaje'],
        'telefono' => $datos['telefono'],
        'fecha_consumo' => $timestamp,
        'cuerpo' => 'Registro consumido por un consumidor(TELEGRAM)',
    ];

    $msglog = new AMQPMessage(json_encode($log_data), ['delivery_mode' => 2]);
    $channel->basic_publish($msglog, '', 'logs');

    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

$channel->basic_qos(null, 1, null);
$channel->basic_consume('cola_telegram', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}


$channel->close();
$connection->close();


