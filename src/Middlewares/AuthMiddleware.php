<?php

namespace App\Middlewares;

use App\Utils\Response;

class AuthMiddleware
{
    public static function handle()
    {
        error_log("AuthMiddleware: Iniciando verificación de autenticación");

        if (session_status() === PHP_SESSION_NONE) {
            error_log("AuthMiddleware: Iniciando sesión PHP");
            session_start();
        }

        $sessionId = session_id();
        $clienteId = $_SESSION['cliente_id'] ?? null;
        $usuarioId = $_SESSION['usuario_id'] ?? null;

        error_log("AuthMiddleware: Session ID: $sessionId");
        error_log("AuthMiddleware: Cliente ID: " . ($clienteId ?? 'NO'));
        error_log("AuthMiddleware: Usuario ID: " . ($usuarioId ?? 'NO'));

        if (!$usuarioId && !$clienteId) {
            error_log("AuthMiddleware: ERROR - No hay usuario ni cliente autenticado");
            Response::error('No autenticado', 401);
            exit;
        }

        error_log("✅ AuthMiddleware: OK - Usuario autenticado");
    }

    public static function checkRole($allowedRoles)
    {
        self::handle();

        // Para staff
        if (isset($_SESSION['usuario_rol'])) {
            if (!in_array($_SESSION['usuario_rol'], $allowedRoles)) {
                Response::error('Acceso no autorizado', 403);
                exit;
            }
        }

        // Para Clientes (Solo pueden acceder a sus recursos)
        elseif (isset($_SESSION['cliente_id'])) {
            if (!in_array('Cliente', $allowedRoles)) {
                Response::error('Acceso no autorizado', 403);
                exit;
            }
        }
    }

    public static function getCurrentUser()
    {
        if (isset($_SESSION['usuario_id'])) {
            self::handle();
            return $_SESSION['usuario_id'] ? [
                'id' => $_SESSION['usuario_id'],
                'nombre' => $_SESSION['usuario_nombre'],
                'email' => $_SESSION['usuario_email'],
                'rol' => $_SESSION['usuario_rol']
            ] : null;
        }
        return null;
    }

    public static function getCurrentCliente()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['cliente_id'])) {
            return [
                'id' => $_SESSION['cliente_id'],
                'nombre' => $_SESSION['cliente_nombre'],
                'email' => $_SESSION['cliente_email']
            ];
        }
        return null;
    }
}
