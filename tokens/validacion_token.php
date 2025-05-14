<?php
header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';

// Conexión a PostgreSQL
try {
    $pdo = new PDO("pgsql:host=postgres_db;dbname=EstructuraMicroservicios", 'ATMadmin', 'ATMadmin_1243', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => false, 'message' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

// Obtener el token desde el header
function obtenerBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $coincidencias)) {
        return $coincidencias[1];
    }
    return null;
}

$token = obtenerBearerToken();

if (!$token) {
    echo json_encode(['status' => false, 'message' => 'Sin permiso']);
    exit;
}

// Validar token
$stmt = $pdo->prepare("SELECT * FROM Tokens WHERE token = :token");
$stmt->execute(['token' => $token]);
$infoToken = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$infoToken || $infoToken["estado"] === "expirado") {
    echo json_encode(['status' => false, 'message' => 'Sin permiso']);
    exit;
}

// Obtener tipo de token y límite
$stmt = $pdo->prepare("
    SELECT l.id AS id_limite, ti.tipo, l.NumVeces
    FROM Tokens t
    JOIN limite_token l ON t.id_limite_token = l.id
    JOIN Tipo_token ti ON l.id_tipo = ti.id
    WHERE t.token = :token
");
$stmt->execute(['token' => $token]);
$infoTipo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$infoTipo) {
    echo json_encode(['status' => false, 'message' => 'Sin permiso']);
    exit;
}

$idLimite = $infoTipo["id_limite"];
$tipoToken = strtolower($infoTipo["tipo"]);
$numVeces = $infoTipo["NumVeces"];

// Validación tipo finito
if ($tipoToken === "finito") {
    $stmt = $pdo->prepare("SELECT COUNT(id) as usos FROM Usos WHERE token = :token");
    $stmt->execute(['token' => $token]);
    $usos = $stmt->fetch(PDO::FETCH_ASSOC)["usos"];

    if ($usos >= $numVeces) {
        echo json_encode(['status' => false, 'message' => 'Sin saldo']);
        exit;
    }
}

// Validación tipo mensual
if ($tipoToken === "mensual") {
    $stmt = $pdo->prepare("
        SELECT COUNT(id) as usos
        FROM Usos
        WHERE token = :token
        AND EXTRACT(MONTH FROM fecha_uso) = EXTRACT(MONTH FROM CURRENT_DATE)
        AND EXTRACT(YEAR FROM fecha_uso) = EXTRACT(YEAR FROM CURRENT_DATE)
    ");
    $stmt->execute(['token' => $token]);
    $usos = $stmt->fetch(PDO::FETCH_ASSOC)["usos"];

    if ($usos >= $numVeces) {
        echo json_encode(['status' => false, 'message' => 'Sin saldo mensual']);
        exit;
    }
}

// Registrar el uso si todo es válido
$stmt = $pdo->prepare("INSERT INTO Usos (token, fecha_uso) VALUES (:token, CURRENT_TIMESTAMP)");
$stmt->execute(['token' => $token]);

// Confirmar uso registrado y token válido
echo json_encode(['status' => true, 'message' => 'Token válido y uso registrado']);