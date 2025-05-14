<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$thresholdPerConsumer = 10;
$maxConsumers = 5;

$prometheusUrl = 'http://prometheus:9090/api/v1/query';
$query = 'rabbitmq_queue_messages_ready{queue="correos"}';

// Conexión a RabbitMQ
$connection = new AMQPStreamConnection('rabbitmq', 5672, 'ATMadmin', 'ATMadmin_1243');
$channel = $connection->channel();
$channel->queue_declare('logs', false, true, false, false);

// Verifica si la imagen base ya existe
$rawOutput = shell_exec("docker images -q pruebas_consumer_email");
$imageExists = trim($rawOutput ?? '');
if ($imageExists === '') {
    echo "Construyendo imagen base 'pruebas_consumer_email'...\n";
    $projectRoot = realpath(__DIR__ . '/../consumer_email');
    $output = shell_exec("docker build -t pruebas_consumer_email " . escapeshellarg("{$projectRoot}") . " 2>&1");
    echo $output;
}

while (true) {
    echo "\n--- Verificando cola 'correos' ---\n";

    // Consultar Prometheus
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $prometheusUrl . '?query=' . urlencode($query));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $pendingMessages = isset($data['data']['result'][0]['value'][1]) ? (int)$data['data']['result'][0]['value'][1] : 0;
    echo "[" . date('H:i:s') . "] Mensajes pendientes: $pendingMessages\n";

    $neededConsumers = min(ceil($pendingMessages / $thresholdPerConsumer), $maxConsumers);
    echo "Consumidores necesarios: $neededConsumers\n";

    // Ver consumidores activos
    $existing = shell_exec("docker ps --filter 'name=consumer_email_' --format '{{.Names}}'");
    $runningConsumers = array_filter(explode("\n", trim($existing ?? '')));
    $runningCount = count($runningConsumers);

    echo "Consumidores actuales: $runningCount\n";

    // Escalar hacia arriba
    if ($neededConsumers > $runningCount) {
        for ($i = $runningCount + 1; $i <= $neededConsumers; $i++) {
            $name = "consumer_email_$i";
            echo "Creando $name...\n";
            shell_exec("docker run -d --name $name --network rabbitmq_network pruebas_consumer_email");

            $log_data = [
                'evento' => 'creacion',
                'contenedor' => $name,
                'timestamp' => date('c')
            ];
            $msglog = new AMQPMessage(json_encode($log_data), ['delivery_mode' => 2]);
            $channel->basic_publish($msglog, '', 'logs');
        }
    }

    // Escalar hacia abajo
    if ($neededConsumers < $runningCount) {
        for ($i = $runningCount; $i > $neededConsumers; $i--) {
            $name = "consumer_email_$i";
            echo "Eliminando $name...\n";
            shell_exec("docker stop $name && docker rm $name");

            $log_data = [
                'evento' => 'eliminacion',
                'contenedor' => $name,
                'timestamp' => date('c')
            ];
            $msglog = new AMQPMessage(json_encode($log_data), ['delivery_mode' => 2]);
            $channel->basic_publish($msglog, '', 'logs');
        }
    }

    sleep(5);
}
