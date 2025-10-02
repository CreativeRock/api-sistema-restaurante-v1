<?php

// Configurar sesiones seguras
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// Al inicio del archivo, después de los headers
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);

//manejar CORS y OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: http://localhost');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 3600');
    http_response_code(200);
    exit();
}

//configurar headers para requests normales
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
//// require_once __DIR__ . '/../src/Config/Database.php';
//// require_once __DIR__ . '/../src/Routes/Router.php';
//// require_once __DIR__ . '/../src/Controllers/BaseController.php';

// //Controladores
//// require_once __DIR__ . '/../src/Controllers/ClienteController.php';
//// require_once __DIR__ . '/../src/Controllers/AuthController.php';
//// require_once __DIR__ . '/../src/Controllers/UsuarioController.php';
//// require_once __DIR__ . '/../src/Controllers/RolController.php';

// //Modelos
//// require_once __DIR__ . '/../src/Models/Cliente.php';
//// require_once __DIR__ . '/../src/Models/Usuario.php';
//// require_once __DIR__ . '/../src/Models/Rol.php';
//// require_once __DIR__ . '/../src/Utils/Response.php';

use App\Routes\Router;
use App\Config\Database;

try {
    Database::init();

    $router = new Router();
    $router->handleRequest();

} catch (Exception $error) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error interno del servidor',
        'details' => $error->getMessage()
    ]);
}

// Después del require del autoloader
spl_autoload_register(function ($class) {
    error_log("Intentando cargar: " . $class);
});

// O verifica si la clase ya existe
if (class_exists('App\Controllers\AuthController')) {
    error_log("AuthController YA está cargado");
} else {
    error_log("AuthController NO está cargado");
}