<?php
namespace App\Routes;

use App\Utils\Response;
use App\Middlewares\AuthMiddleware;

class Router
{
    private $routes = [];

    public function __construct()
    {
        $this->defineRoutes();
    }

    private function defineRoutes()
    {
        //AUTENTICACIÓN
        // Auth para personal del restaurante (NO protegidas)
        $this->addRoute('POST', 'auth/login', 'AuthController', 'login', false);
        $this->addRoute('POST', 'auth/logout', 'AuthController', 'logout', true);
        $this->addRoute('GET', 'auth/me', 'AuthController', 'me', true);

        // Auth para clientes (login y register NO protegidos)
        $this->addRoute('POST', 'clientes/auth/login', 'ClienteAuthController', 'login', false);
        $this->addRoute('POST', 'clientes/auth/logout', 'ClienteAuthController', 'logout', true);
        $this->addRoute('GET', 'clientes/auth/me', 'ClienteAuthController', 'me', true);
        $this->addRoute('POST', 'clientes/auth/register', 'ClienteAuthController', 'register', false);

        //CLIENTES
        $this->addRoute('GET', 'clientes', 'ClienteController', 'index', true);
        $this->addRoute('GET', 'clientes/{id}', 'ClienteController', 'show', true);
        $this->addRoute('POST', 'clientes', 'ClienteController', 'store', true);
        $this->addRoute('PUT', 'clientes/{id}', 'ClienteController', 'update', true);
        $this->addRoute('DELETE', 'clientes/{id}', 'ClienteController', 'delete', true);

        //PERFIL
        $this->addRoute('GET', 'clientes/perfil', 'ClienteController', 'getProfile', true);
        $this->addRoute('PUT', 'clientes/perfil', 'ClienteController', 'updateProfile', true);
        $this->addRoute('PUT', 'clientes/cambiar-password', 'ClienteController', 'changePassword', true);

        //USUARIOS (PERSONAL)
        $this->addRoute('GET', 'usuarios', 'UsuarioController', 'index', true);
        $this->addRoute('GET', 'usuarios/{id}', 'UsuarioController', 'show', true);
        $this->addRoute('POST', 'usuarios', 'UsuarioController', 'store', true);
        $this->addRoute('PUT', 'usuarios/{id}', 'UsuarioController', 'update', true);
        $this->addRoute('DELETE', 'usuarios/{id}', 'UsuarioController', 'delete', true);
        $this->addRoute('GET', 'usuarios/rol/{id_rol}', 'UsuarioController', 'getByRole', true);
        $this->addRoute('GET', 'usuarios/buscar/{termino}', 'UsuarioController', 'search', true);
        $this->addRoute('GET', 'usuarios/estadisticas/conteo', 'UsuarioController', 'stats', true);
        $this->addRoute('PUT', 'usuarios/{id}/cambiar-password', 'UsuarioController', 'changePassword', true);
        $this->addRoute('GET', 'usuarios/{id}/perfil', 'UsuarioController', 'profile', true);

        //ROLES
        $this->addRoute('GET', 'roles', 'RolController', 'index', true);
        $this->addRoute('GET', 'roles/{id}', 'RolController', 'show', true);
        $this->addRoute('POST', 'roles', 'RolController', 'store', true);
        $this->addRoute('PUT', 'roles/{id}', 'RolController', 'update', true);
        $this->addRoute('DELETE', 'roles/{id}', 'RolController', 'delete', true);
        $this->addRoute('GET', 'roles/{id}/usuarios', 'RolController', 'getUserCount', true);
        $this->addRoute('GET', 'roles/buscar/{termino}', 'RolController', 'search', true);
        $this->addRoute('GET', 'roles/estadisticas/conteo', 'RolController', 'stats', true);
        $this->addRoute('GET', 'roles/sistema/lista', 'RolController', 'systemRoles', true);

        //MESAS
        $this->addRoute('GET', 'mesas', 'MesaController', 'index', false); // Pública
        $this->addRoute('POST', 'mesas', 'MesaController', 'store', true);
        $this->addRoute('GET', 'mesas/{id}', 'MesaController', 'show', false); // Pública
        $this->addRoute('PUT', 'mesas/{id}', 'MesaController', 'update', true);
        $this->addRoute('DELETE', 'mesas/{id}', 'MesaController', 'delete', true);
        $this->addRoute('GET', 'mesas/disponibles', 'MesaController', 'getAvailable', false); // Pública
        $this->addRoute('GET', 'mesas/estadisticas', 'MesaController', 'getStatistics', true);
        $this->addRoute('GET', 'mesas/enums', 'MesaController', 'getEnumValues', false); // Pública
        $this->addRoute('GET', 'mesas/tipo/{tipo}', 'MesaController', 'getByType', false); // Pública
        $this->addRoute('GET', 'mesas/estado/{estado}', 'MesaController', 'getByStatus', true);
        $this->addRoute('PUT', 'mesas/{id}/estado', 'MesaController', 'changeStatus', true);
        $this->addRoute('GET', 'mesas/disponibilidad', 'MesaController', 'checkDisponibilidad', false); // Pública

        //RESERVAS CLIENTES
        $this->addRoute('GET', 'cliente/reservas/disponibilidad', 'ClienteReservaController', 'checkAvailability', true);
        $this->addRoute('PUT', 'cliente/reservas/{id}/cancelar', 'ClienteReservaController', 'cancel', true);
        $this->addRoute('GET', 'cliente/reservas/{id}', 'ClienteReservaController', 'show', true);
        $this->addRoute('GET', 'cliente/reservas', 'ClienteReservaController', 'index', true);
        $this->addRoute('POST', 'cliente/reservas', 'ClienteReservaController', 'store', true);
        $this->addRoute('PUT', 'cliente/reservas/{id}', 'ClienteReservaController', 'update', true);

        //RESERVAS STAFF
        $this->addRoute('GET', 'staff/reservas/disponibilidad', 'StaffReservaController', 'checkAvailability', true);
        $this->addRoute('GET', 'staff/reservas/estadisticas', 'StaffReservaController', 'getStats', true);
        $this->addRoute('GET', 'staff/reservas/pendientes-confirmacion', 'StaffReservaController', 'getPendingConfirmation', true);
        $this->addRoute('GET', 'staff/reservas', 'StaffReservaController', 'index', true);
        $this->addRoute('GET', 'staff/reservas/{id}', 'StaffReservaController', 'show', true);
        $this->addRoute('POST', 'staff/reservas', 'StaffReservaController', 'store', true);
        $this->addRoute('PUT', 'staff/reservas/{id}', 'StaffReservaController', 'update', true);
        $this->addRoute('PUT', 'staff/reservas/{id}/cancelar', 'StaffReservaController', 'cancel', true);
        $this->addRoute('DELETE', 'staff/reservas/{id}', 'StaffReservaController', 'delete', true);
        $this->addRoute('GET', 'staff/reservas/{id}/historial', 'StaffReservaController', 'getHistorial', true);
        $this->addRoute('PUT', 'staff/reservas/{id}/estado', 'StaffReservaController', 'changeStatus', true);
        $this->addRoute('GET', 'staff/reservas/cliente/{id_cliente}', 'StaffReservaController', 'getByCliente', true);
        $this->addRoute('GET', 'staff/reservas/fecha/{fecha}', 'StaffReservaController', 'getByDate', true);
        $this->addRoute('GET', 'staff/reservas/estado/{estado}', 'StaffReservaController', 'getByStatus', true);
        $this->addRoute('GET', 'staff/reservas/proximas', 'StaffReservaController', 'getUpcoming', true);

        //HORARIOS
        $this->addRoute('GET', 'horarios', 'HorarioController', 'index', false); // Pública
        $this->addRoute('GET', 'horarios/{id}', 'HorarioController', 'show', false); // Pública
        $this->addRoute('POST', 'horarios', 'HorarioController', 'store', true);
        $this->addRoute('PUT', 'horarios/{id}', 'HorarioController', 'update', true);
        $this->addRoute('DELETE', 'horarios/{id}', 'HorarioController', 'delete', true);
        $this->addRoute('GET', 'horarios/dia/{dia}', 'HorarioController', 'getByDay', false); // Pública
    }

    // Agregar ruta con parámetro de protección
    private function addRoute($method, $path, $controller, $action, $protected = false)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action,
            'protected' => $protected
        ];
    }

    // Procesar peticion
    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getUri();

         // Buscar coincidencia con ruta
        $route = $this->matchRoute($method, $uri);

        if (!$route) {
            Response::notFound('Ruta no encontrada');
            return;
        }

        // Verificar autenticación si la ruta es protegida
        if ($route['protected']) {
            error_log("Router: Ruta protegida, verificando autenticación");

            try {
                AuthMiddleware::handle();
                error_log("Router: Autenticación exitosa");
            } catch (\Exception $e) {
                error_log("Router: Error de autenticación - " . $e->getMessage());
                // El middleware ya envió la respuesta, solo salir
                return;
            }
        } else {
            error_log("Router: Ruta pública, sin verificación de autenticación");
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
                error_log("Router: Ruta encontrada - " . $route['path']);
                return [
                    'controller' => $route['controller'],
                    'action' => $route['action'],
                    'params' => $params,
                    'protected' => $route['protected']
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
            error_log("Router: Controlador no encontrado - $controllerClass");
            Response::error('Controlador no encontrado', 500);
            return;
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $action)) {
            error_log("Router: Método no encontrado - $controllerClass::$action");
            Response::error('Método no encontrado', 500);
            return;
        }

        error_log("Router: Ejecutando $controllerClass::$action");
        call_user_func_array([$controller, $action], $params);
    }
}
