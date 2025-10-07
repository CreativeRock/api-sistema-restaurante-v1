<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\Usuario;
use App\Utils\Response;

class AuthController extends BaseController
{
    private $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new Usuario();
    }

    //POST /auth/login - Iniciar sesión
    public function login()
    {
        // Verificar si la sesión ya está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            // Configurar cookies antes de iniciar sesión
            session_set_cookie_params([
                'lifetime' => 86400, // 24 horas
                'path' => '/',
                'domain' => 'localhost',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }

        $data = $this->getJsonInput();
        if (!$data || !isset($data['email']) || !isset($data['password'])) {
            Response::error('Email y contraseña son requeridos');
            return;
        }

        try {
            $usuario = $this->usuarioModel->authenticate($data['email'], $data['password']);

            if (!$usuario) {
                Response::error('Credenciales inválidas', 401);
                return;
            }

            // Regenerar ID de sesión para prevenir fixation attacks
            session_regenerate_id(true);

            // Guardar datos en sesión
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_rol'] = $usuario['nombre_rol'];
            $_SESSION['usuario_rol_id'] = $usuario['id_rol'];
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();

            // Log para debugging
            error_log("Login exitoso - Usuario ID: " . $usuario['id_usuario']);
            error_log("Session ID: " . session_id());

            Response::success([
                'id' => $usuario['id_usuario'],
                'nombre' => $usuario['nombre'] . ' ' . $usuario['apellido'],
                'email' => $usuario['email'],
                'rol' => $usuario['nombre_rol']
            ], 'Login exitoso');

        } catch (\Exception $error) {
            error_log("Error en login: " . $error->getMessage());
            Response::error('Error en el login: ' . $error->getMessage(), 500);
        }
    }

    //POST /auth/logout - Cerrar sesión
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Limpiar datos de sesión
        $_SESSION = [];

        // Destruir la sesión y cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        Response::success(null, 'Sesión cerrada correctamente');
    }

    //GET /auth/me - obtener usuario actual
    public function me()
    {
        // Configurar sesión con los mismos parámetros
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                'domain' => 'localhost',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }

        // Log para debugging
        error_log("ME endpoint - Session ID: " . session_id());
        error_log("ME endpoint - Usuario ID en sesión: " . ($_SESSION['usuario_id'] ?? 'NO'));

        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['logged_in'])) {
            error_log("ME endpoint - ERROR: No autenticado");
            Response::error('No autenticado', 401);
            return;
        }

        // Verificar si la sesión ha expirado (24 horas)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
            session_destroy();
            Response::error('Sesión expirada', 401);
            return;
        }

        // Actualizar tiempo de actividad
        $_SESSION['last_activity'] = time();

        Response::success([
            'id' => $_SESSION['usuario_id'],
            'nombre' => $_SESSION['usuario_nombre'],
            'email' => $_SESSION['usuario_email'],
            'rol' => $_SESSION['usuario_rol']
        ], 'Usuario actual');
    }
}