<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$thresholdPerConsumer = 10000;

$prometheusUrl = 'http://prometheus:9090/api/v1/query';
$query = 'rabbitmq_queue_messages_ready{queue="whatsapp"}';

// Conexión a RabbitMQ
$connection = new AMQPStreamConnection('rabbitmq', 5672, 'ATMadmin', 'ATMadmin_1243');
$channel = $connection->channel();
$channel->queue_declare('logs', false, true, false, false);

// Verifica si la imagen base ya existe
$rawOutput = shell_exec("docker images -q img_consumer_whatsapp");
$imageExists = trim($rawOutput ?? '');
if ($imageExists === '') {
    echo "Construyendo imagen base 'img_consumer_whatsapp'...\n";
    $projectRoot = realpath(__DIR__ . '/../consumer_whatsapp');
    $output = shell_exec("docker build -t img_consumer_whatsapp " . escapeshellarg("{$projectRoot}") . " 2>&1");
    echo $output;
}

while (true) {
    echo "\n--- Verificando cola 'whatsapp' ---\n";

    // Consultar Prometheus
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $prometheusUrl . '?query=' . urlencode($query));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $pendingMessages = isset($data['data']['result'][0]['value'][1]) ? (int)$data['data']['result'][0]['value'][1] : 0;
    echo "[" . date('H:i:s') . "] Mensajes pendientes: $pendingMessages\n";

    $neededConsumers = ceil($pendingMessages / $thresholdPerConsumer);
    echo "Consumidores necesarios: $neededConsumers\n";

    // Ver consumidores activos
    $existing = shell_exec("docker ps --filter 'name=consumer_whatsapp_' --format '{{.Names}}'");
    $runningConsumers = array_filter(explode("\n", trim($existing ?? '')));
    $runningCount = count($runningConsumers);

    echo "Consumidores actuales: $runningCount\n";

    // Escalar hacia arriba
    if ($neededConsumers > $runningCount) {
        $commands = [];

        for ($i = $runningCount + 1; $i <= $neededConsumers; $i++) {
            $name = "consumer_whatsapp_$i";
            echo "Creando $name...\n";

            $commands[] = "docker run -d --name $name --network rabbitmq_network img_consumer_whatsapp &";

            $log_data = [
                'evento' => 'creacion',
                'contenedor' => $name,
                'timestamp' => date('c')
            ];
            $msglog = new AMQPMessage(json_encode($log_data), ['delivery_mode' => 2]);
            $channel->basic_publish($msglog, '', 'logs');
        }

        // Ejecutar todos los comandos a la vez
        foreach ($commands as $cmd) {
            shell_exec($cmd);
        }
    }

    // Escalar hacia abajo
    if ($neededConsumers < $runningCount) {
        $toRemove = [];

        for ($i = $runningCount; $i > $neededConsumers; $i--) {
            $name = "consumer_whatsapp_$i";
            echo "Marcado para eliminación: $name\n";
            $toRemove[] = $name;

            // Registrar evento
            $log_data = [
                'evento' => 'eliminacion',
                'contenedor' => $name,
                'timestamp' => date('c')
            ];
            $msglog = new AMQPMessage(json_encode($log_data), ['delivery_mode' => 2]);
            $channel->basic_publish($msglog, '', 'logs');
        }

        if (!empty($toRemove)) {
            $names = implode("\n", $toRemove);
            echo "Eliminando contenedores en paralelo...\n";
            shell_exec("echo \"$names\" | xargs -P 4 -n 1 docker rm -f");
        }
    }



}
