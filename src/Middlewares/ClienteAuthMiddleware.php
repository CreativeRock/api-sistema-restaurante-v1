<?php

namespace App\Middlewares;

use App\Utils\Response;

class ClienteAuthMiddleware
{
    public static function handle()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['cliente_id'])) {
            Response::error('Cliente no autenticado', 401);
            exit;
        }
    }

    public static function getCurrentCliente()
    {
        if (isset($_SESSION['cliente_id'])) {
            return [
                'id' => $_SESSION['cliente_id'],
                'nombre' => $_SESSION['cliente_nombre'],
                'email' => $_SESSION['cliente_email']
            ];
        }

        return null;
    }

    public static function checkClienteAccess($clienteId)
    {
        self::handle();

        if ($_SESSION['cliente_id'] != $clienteId) {
            Response::error('No tiene permisos para acceder a este recurso', 403);
            exit;
        }
    }
}
