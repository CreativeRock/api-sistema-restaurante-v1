<?php
// src/Routes/Router.php

namespace App\Routes;

use App\Controllers\MesaController;
use App\Controllers\ReservaController;
use App\Models\HistorialReserva;
use App\Utils\Response;

class Router
{
    private $routes = [];

    public function __construct()
    {
        $this->defineRoutes();
    }

    // Definir todas las rutas necesarias clientes, mesas etc
    private function defineRoutes()
    {
        // ==================== AUTENTICACIÓN ====================
        // Auth para personal del restaurante
        $this->addRoute('POST', 'auth/login', 'AuthController', 'login');
        $this->addRoute('POST', 'auth/logout', 'AuthController', 'logout');
        $this->addRoute('GET', 'auth/me', 'AuthController', 'me');

        // Auth para clientes
        $this->addRoute('POST', 'clientes/auth/login', 'ClienteAuthController', 'login');
        $this->addRoute('POST', 'clientes/auth/logout', 'ClienteAuthController', 'logout');
        $this->addRoute('GET', 'clientes/auth/me', 'ClienteAuthController', 'me');
        $this->addRoute('POST', 'clientes/auth/register', 'ClienteAuthController', 'register');

        // ==================== CLIENTES ====================
        $this->addRoute('GET', 'clientes', 'ClienteController', 'index');
        $this->addRoute('GET', 'clientes/{id}', 'ClienteController', 'show');
        $this->addRoute('POST', 'clientes', 'ClienteController', 'store');
        $this->addRoute('PUT', 'clientes/{id}', 'ClienteController', 'update');
        $this->addRoute('DELETE', 'clientes/{id}', 'ClienteController', 'delete');

        // ==================== USUARIOS (PERSONAL) ====================
        $this->addRoute('GET', 'usuarios', 'UsuarioController', 'index');
        $this->addRoute('GET', 'usuarios/{id}', 'UsuarioController', 'show');
        $this->addRoute('POST', 'usuarios', 'UsuarioController', 'store');
        $this->addRoute('PUT', 'usuarios/{id}', 'UsuarioController', 'update');
        $this->addRoute('DELETE', 'usuarios/{id}', 'UsuarioController', 'delete');
        $this->addRoute('GET', 'usuarios/rol/{id_rol}', 'UsuarioController', 'getByRole');
        $this->addRoute('GET', 'usuarios/buscar/{termino}', 'UsuarioController', 'search');
        $this->addRoute('GET', 'usuarios/estadisticas/conteo', 'UsuarioController', 'stats');
        $this->addRoute('PUT', 'usuarios/{id}/cambiar-password', 'UsuarioController', 'changePassword');
        $this->addRoute('GET', 'usuarios/{id}/perfil', 'UsuarioController', 'profile');

        // ==================== ROLES ====================
        $this->addRoute('GET', 'roles', 'RolController', 'index');
        $this->addRoute('GET', 'roles/{id}', 'RolController', 'show');
        $this->addRoute('POST', 'roles', 'RolController', 'store');
        $this->addRoute('PUT', 'roles/{id}', 'RolController', 'update');
        $this->addRoute('DELETE', 'roles/{id}', 'RolController', 'delete');
        $this->addRoute('GET', 'roles/{id}/usuarios', 'RolController', 'getUserCount');
        $this->addRoute('GET', 'roles/buscar/{termino}', 'RolController', 'search');
        $this->addRoute('GET', 'roles/estadisticas/conteo', 'RolController', 'stats');
        $this->addRoute('GET', 'roles/sistema/lista', 'RolController', 'systemRoles');

        // ==================== MESAS ====================
        $this->addRoute('GET', 'mesas', 'MesaController', 'index');
        $this->addRoute('POST', 'mesas', 'MesaController', 'store');
        $this->addRoute('GET', 'mesas/{id}', 'MesaController', 'show');
        $this->addRoute('PUT', 'mesas/{id}', 'MesaController', 'update');
        $this->addRoute('DELETE', 'mesas/{id}', 'MesaController', 'delete');
        $this->addRoute('GET', 'mesas/disponibles', 'MesaController', 'getAvailable');
        $this->addRoute('GET', 'mesas/estadisticas', 'MesaController', 'getStatistics');
        $this->addRoute('GET', 'mesas/enums', 'MesaController', 'getEnumValues');
        $this->addRoute('GET', 'mesas/tipo/{tipo}', 'MesaController', 'getByType');
        $this->addRoute('GET', 'mesas/estado/{estado}', 'MesaController', 'getByStatus');
        $this->addRoute('PUT', 'mesas/{id}/estado', 'MesaController', 'changeStatus');
        $this->addRoute('GET', 'mesas/disponibilidad', 'MesaController', 'checkDisponibilidad');

        // ==================== RESERVAS ====================
        $this->addRoute('GET', 'reservas', 'ReservaController', 'index');
        $this->addRoute('POST', 'reservas', 'ReservaController', 'store');
        $this->addRoute('GET', 'reservas/{id}', 'ReservaController', 'show');
        $this->addRoute('PUT', 'reservas/{id}', 'ReservaController', 'update');
        $this->addRoute('DELETE', 'reservas/{id}', 'ReservaController', 'delete');
        $this->addRoute('PUT', 'reservas/{id}/cancelar', 'ReservaController', 'cancel');
        $this->addRoute('GET', 'reservas/{id}/historial', 'ReservaController', 'getHistorial');
        $this->addRoute('GET', 'reservas/cliente/{id_cliente}', 'ReservaController', 'getByCliente');
        $this->addRoute('GET', 'reservas/fecha/{fecha}', 'ReservaController', 'getByDate');
        $this->addRoute('GET', 'reservas/estado/{estado}', 'ReservaController', 'getByStatus');
        $this->addRoute('PUT', 'reservas/{id}/estado', 'ReservaController', 'changeStatus');
        $this->addRoute('GET', 'reservas/disponibilidad', 'ReservaController', 'checkAvailability');

        // ==================== HORARIOS ====================
        $this->addRoute('GET', 'horarios', 'HorarioController', 'index');
        $this->addRoute('GET', 'horarios/{id}', 'HorarioController', 'show');
        $this->addRoute('POST', 'horarios', 'HorarioController', 'store');
        $this->addRoute('PUT', 'horarios/{id}', 'HorarioController', 'update');
        $this->addRoute('DELETE', 'horarios/{id}', 'HorarioController', 'delete');
        $this->addRoute('GET', 'horarios/dia/{dia}', 'HorarioController', 'getByDay');
    }

    // Agregar ruta
    private function addRoute($method, $path, $controller, $action)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action
        ];
    }

    // Procesar peticion
    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getUri();

        // Debug temporal - puedes eliminarlo después
        error_log("Método: $method, URI: '$uri'");
        error_log("Rutas registradas: " . print_r($this->routes, true));

        // buscar coincidencia con ruta
        $route = $this->matchRoute($method, $uri);

        if (!$route) {
            Response::notFound('Ruta no encontrada');
            return;
        }

        // Ejecutar controlador
        $this->executeController($route['controller'], $route['action'], $route['params']);
    }

    // Obtener la URI limpia
    private function getUri()
    {
        $uri = $_SERVER['REQUEST_URI'];

        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remover el path base de tu proyecto
        $uri = str_replace('/api-sistema-restaurante-g2-v1/public', '', $uri);

        $uri = trim($uri, '/');

        // Debug temporal
        error_log("URI original: " . $_SERVER['REQUEST_URI']);
        error_log("URI procesada: '$uri'");

        return $uri;
    }

    // Buscar coincidencia con la peticion
    private function matchRoute($method, $uri)
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchPath($route['path'], $uri);

            if ($params !== false) {
                return [
                    'controller' => $route['controller'],
                    'action' => $route['action'],
                    'params' => $params
                ];
            }
        }
        return null;
    }

    // Path coicide con la URI
    private function matchPath($routePath, $uri)
    {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (preg_match($pattern, $uri, $matches)) {
            array_shift($matches);
            return $matches;
        }

        return false;
    }

    // Ejecutar el controlador
    private function executeController($controllerName, $action, $params)
    {
        $controllerClass = "App\\Controllers\\$controllerName";

        if (!class_exists($controllerClass)) {
            Response::error('Controlador no encontrado', 500);
            return;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $action)) {
            Response::error('Método no encontrado', 500);
            return;
        }

        call_user_func_array([$controller, $action], $params);
    }
}
