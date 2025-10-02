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

        //Verificar si la sesiones están iniciadas
        if (session_status() === PHP_SESSION_NONE) {
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

            //Guardar datos en sesión
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_rol'] = $usuario['nombre_rol'];
            $_SESSION['usuario_rol_id'] = $usuario['id_rol'];

            Response::success([
                'id' => $usuario['id_usuario'],
                'nombre' => $usuario['nombre'] . ' ' . $usuario['apellido'],
                'email' => $usuario['email'],
                'rol' => $usuario['nombre_rol']
            ], 'Login exitoso');


        } catch (\Exception $error) {
            Response::error('Error en el login: ', $error->getMessage(), 500);
        }
    }

    //POST /auth/logout - Cerrar sesión
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        //Destruir la sesión
        session_destroy();

        Response::success(null, 'Sesión cerrada correctamente');
    }

    //GET /auth/me - obtener usuario actual
    public function me()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['usuario_id'])) {
            Response::error('No autenticado', 401);
            return;
        }

        Response::success([
            'id' => $_SESSION['usuario_id'],
            'nombre' => $_SESSION['usuario_nombre'],
            'email' => $_SESSION['usuario_email'],
            'rol' => $_SESSION['usuario_rol']
        ], 'Usuario actual');
    }

}