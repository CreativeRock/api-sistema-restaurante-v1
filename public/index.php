<?php

// Configurar sesiones seguras
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax'); // Cambiar de 'None' a 'Lax' para desarrollo local
ini_set('session.cookie_secure', 0); // 0 para desarrollo local

// Lista de orígenes permitidos
$allowed_origins = [
    'http://localhost:4200',
    'http://127.0.0.1:4200',
    'http://localhost:5173',
    'http://127.0.0.1:5173'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Headers CORS - DEBEN ir al inicio, antes de cualquier output
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Para desarrollo, permitir el origen actual
    header("Access-Control-Allow-Origin: http://localhost:4200");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN, Accept, Origin, Cookie");
header("Access-Control-Expose-Headers: Authorization, Set-Cookie");
header("Access-Control-Max-Age: 86400");

// Manejar preflight (OPTIONS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración de headers para requests normales
header('Content-Type: application/json; charset=utf-8');

// Configurar cookies de sesión ANTES de iniciar sesión
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'localhost',
    'secure' => false, // false para desarrollo local
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Iniciar sesión DESPUÉS de configurar cookies
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';

use App\Routes\Router;
use App\Config\Database;

try {
    Database::init();

    $router = new Router();
    $router->handleRequest();

} catch (Exception $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'details' => $error->getMessage()
    ]);
}