<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\Cliente;
use App\Utils\Response;

class ClienteAuthController extends BaseController
{
    private $clienteModel;

    public function __construct()
    {
        $this->clienteModel = new Cliente();
    }

    //POST /clientes/auth/login - Login para clientes
    public function login()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $data = $this->getJsonInput();
        if (!$data || !isset($data['email']) || !isset($data['password'])) {
            Response::error('Email y contraseña son requeridos');
            return;
        }

        try {
            $cliente = $this->clienteModel->autenticate($data['email'], $data['password']);

            if (!$cliente) {
                Response::error('Credenciales inválidas', 401);
                return;
            }

            $_SESSION['cliente_id'] = $cliente['id_cliente'];
            $_SESSION['cliente_nombre'] = $cliente['nombre'] . ' ' . $cliente['apellido'];
            $_SESSION['cliente_email'] = $cliente['email'];

            Response::success([
                'id' => $cliente['id_cliente'],
                'nombre' => $cliente['nombre'] . ' ' . $cliente['apellido'],
                'email' => $cliente['email']
            ], 'Login exitoso como cliente');
        } catch (\Exception $error) {
            Response::error('Error en el login: ', $error->getMessage(), 500);
        }
    }

    //POST /clientes/auth/logout - Logout para clientes
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Eliminar solo las variables de sesión del cliente
        unset($_SESSION['cliente_id']);
        unset($_SESSION['cliente_nombre']);
        unset($_SESSION['cliente_email']);

        Response::success(null, 'Sesión de cliente cerrada correctamente');
    }

    //GET /clientes/auth/me - Obtener cliente actual
    public function me()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['cliente_id'])) {
            Response::error('Cliente no autenticado', 401);
            return;
        }

        Response::success([
            'id' => $_SESSION['cliente_id'],
            'nombre' => $_SESSION['cliente_nombre'],
            'email' => $_SESSION['cliente_email']
        ], 'Cliente actual');
    }

    //POST /clientes/auth/register - Registro de nuevos clientes
    public function register()
    {
        $data = $this->getJsonInput();
        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        //Validar campos requeridos
        if (!isset($data['nombre']) || !isset($data['apellido']) || !isset($data['email']) || !isset($data['password']) || !isset($data['telefono'])) {
            Response::error('Todos los campos son requeridos: nombre, apellido, email, password, telefono');
            return;
        }

        try {
            //verificar si el email ya existe
            if ($this->clienteModel->emailExists($data['email'])) {
                Response::validationError(['email' => 'El email ya está registrado']);
                return;
            }

            //Crear el cliente
            $cliente = $this->clienteModel->create($data);

            if (!$cliente) {
                Response::error('Error al registrar cliente', 500);
                return;
            }

            //Eliminar password de la respuesta
            if (isset($cliente['password'])) {
                unset($cliente['password']);
            }

            Response::success($cliente, 'Cliente registrado correctamente', 201);
        } catch (\Exception $error) {
            Response::error('Error al registrar cliente', $error->getMessage(), 500);
        }
    }
}
