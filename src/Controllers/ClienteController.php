<?php

namespace App\Controllers;

use App\Middlewares\AuthMiddleware;
use App\Models\Cliente;
use App\Utils\Response;
use App\Middlewares\ClienteAuthMiddleware;
use App\Middlewares\RolMiddleware;


class ClienteController extends BaseController
{
    private $clienteModel;

    public function __construct()
    {
        $this->clienteModel = new Cliente();
    }

    //GET /clientes - Obtener todos los clientes (SOLO ADMIN)
    public function index()
    {
        //solo los administradoresp pueden ver todos los clientes
        RolMiddleware::adminAndGerente();

        try {
            $clientes = $this->clienteModel->getAll();
            Response::success($clientes, 'Clientes obtenidos correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener clientes: ' . $error->getMessage(), 500);
        }
    }

    //GET /clientes/id - obtener cliente por ID
    public function show($id)
    {
        // Cliente solo puede ver su propio perfil, admin/gerente puede ver todos
        $currentUser = RolMiddleware::getCurrentUser();

        if ($currentUser && in_array($currentUser['rol'], ['Administrador', 'Gerente'])) {
            //Personal autorizado puede ver cliente
        } else {
            ClienteAuthMiddleware::checkClienteAccess($id);
        }

        if (!$this->isValidId($id)) {
            Response::error('ID de cliente inválido');
            return;
        }

        try {
            $cliente = $this->clienteModel->getById($id);

            if (!$cliente) {
                Response::notFound('Cliente no encontrado');
                return;
            }

            Response::success($cliente, 'Cliente obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener cliente: ' . $error->getMessage(), 500);
        }
    }

    //POST /clientes - Crear nuevo cliente
    public function store()
    {
        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        //Validar campos requeridos
        $this->validateRequerid($data, ['nombre', 'apellido', 'email', 'telefono', 'password']);

        //limpiar datos
        $data = $this->sanitizeInput($data);

        //Validaciones adicionales
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Response::validationError(['email' => 'Formato de email inválido']);
            return;
        }

        //verificar si el email ya existe
        if ($this->clienteModel->emailExists($data['email'])) {
            Response::validationError(['email' => 'El email ya está registrado']);
            return;
        }

        //Agregar preferencias vacias si no se envian
        if (!isset($data['preferencias'])) {
            $data['preferencias'] = '';
        }

        try {
            $cliente = $this->clienteModel->create($data);

            if (!$cliente) {
                Response::error('Error al crear cliente', 500);
                return;
            }

            if (isset($cliente['password'])) {
                unset($cliente['password']);
            }

            Response::success($cliente, 'Cliente creado correctamente', 201);
        } catch (\Exception $error) {
            Response::error('Error al crear cliente: ' . $error->getMessage(), 500);
        }
    }

    //PUT /clientes/id - Actualizar Cliente
    public function update($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de cliente inválido');
        }

        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Response::validationError(['email' => 'Formato de email inválido']);
            return;
        }

        if (isset($data['telefono']) && strlen($data['telefono']) !== 8) {
            Response::validationError(['telefono' => 'El teléfono debe tener exactamente 8 dígitos']);
            return;
        }

        // Verificar si el cliente existe
        if (!$this->clienteModel->getById($id)) {
            Response::notFound('Cliente no encontrado');
            return;
        }

        // Verificar si el email ya existe (excluyendo el cliente actual)
        if (isset($data['email']) && $this->clienteModel->emailExists($data['email'], $id)) {
            Response::validationError(['email' => 'El email ya está registrado']);
            return;
        }

        //Limpiar Datos
        $data = $this->sanitizeInput($data);

        try {
            $cliente = $this->clienteModel->update($id, $data);

            if (!$cliente) {
                Response::error('Error al actualizar cliente', 500);
                return;
            }

            if (isset($cliente['password'])) {
                unset($cliente['password']);
            }

            Response::success($cliente, 'Cliente actualizado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al actualizar cliente: ' . $error->getMessage(), 500);
        }
    }

    //DELETE /clientes/id - Eliminar cliente
    public function delete($id)
    {
        //Solo administradores pueden eliminar clientes
        RolMiddleware::adminOnly();

        if (!$this->isValidId($id)) {
            Response::error('ID de cliente inválido');
            return;
        }

        if (!$this->clienteModel->getById($id)) {
            Response::notFound('Cliente no encontrado');
            return;
        }

        try {
            $result = $this->clienteModel->delete($id);

            if (!$result) {
                Response::error('Error al eliminar cliente', 500);
                return;
            }

            Response::success(null, 'Cliente eliminado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al eliminar cliente: ', $error->getMessage(), 500);
        }
    }

    //Perfil Cliente
    public function getProfile()
    {
        $cliente = AuthMiddleware::getCurrentCliente();

        if (!$cliente) {
            Response::error('Cliente no autenticado', 401);
            return;
        }

        try {
            $clienteCompleto = $this->clienteModel->getById($cliente['id']);

            if (!$clienteCompleto) {
                Response::error('Cliente no encontrado', 404);
                return;
            }

            // Eliminar información sensible
            unset($clienteCompleto['password']);

            Response::success($clienteCompleto, 'Perfil obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener perfil: ' . $error->getMessage(), 500);
        }
    }

    public function updateProfile()
    {
        $cliente = AuthMiddleware::getCurrentCliente();

        if (!$cliente) {
            Response::error('Cliente no autenticado', 401);
            return;
        }

        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        try {
            // Campos permitidos para actualizar
            $allowedFields = ['nombre', 'apellido', 'email', 'telefono', 'preferencias'];
            $updateData = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Validar email único si se está actualizando
            if (isset($updateData['email']) && $updateData['email'] !== $cliente['email']) {
                if ($this->clienteModel->emailExists($updateData['email'])) {
                    Response::validationError(['email' => 'El email ya está registrado']);
                    return;
                }
            }

            $clienteActualizado = $this->clienteModel->update($cliente['id'], $updateData);

            if (!$clienteActualizado) {
                Response::error('Error al actualizar perfil');
                return;
            }

            // Actualizar datos en sesión
            $_SESSION['cliente_nombre'] = $clienteActualizado['nombre'] . ' ' . $clienteActualizado['apellido'];
            $_SESSION['cliente_email'] = $clienteActualizado['email'];

            // Eliminar información sensible
            unset($clienteActualizado['password']);

            Response::success($clienteActualizado, 'Perfil actualizado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al actualizar perfil: ' . $error->getMessage(), 500);
        }
    }
}
