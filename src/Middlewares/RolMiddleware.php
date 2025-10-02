<?php

namespace App\Middlewares;

use App\Utils\Response;

class RolMiddleware
{
    public static function adminOnly()
    {
        self::checkRole(['Admin']);
    }

    public static function adminAndGerente()
    {
        self::checkRole(['Admin', 'Gerente']);
    }

    public static function staffOnly()
    {
        self::checkRole(['Admin', 'Gerente', 'Mesero', 'Recepcionista']);
    }

    public static function anyAuthenticated()
    {
        self::handle();
    }

    public static function handle()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['usuario_id'])) {
            Response::error('No autenticado', 401);
            exit;
        }
    }

    public static function checkRole($allowedRoles)
    {
        self::handle();

        if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], $allowedRoles)) {
            Response::error('No tiene permisos para esta acción', 403);
            exit;
        }
    }

    public static function getCurrentUser()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['usuario_id'])) {
            return [
                'id' => $_SESSION['usuario_id'],
                'nombre' => $_SESSION['usuario_nombre'],
                'email' => $_SESSION['usuario_email'],
                'rol' => $_SESSION['usuario_rol']
            ];
        }

        return null;
    }
}
