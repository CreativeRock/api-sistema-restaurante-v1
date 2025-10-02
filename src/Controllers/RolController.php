<?php

namespace App\Controllers;

use App\Models\Rol;
use App\Utils\Response;
use App\Middlewares\RolMiddleware;


class RolController extends BaseController
{
    private $rolModel;

    public function __construct()
    {
        $this->rolModel = new Rol();
    }

    //GET /roles - Obtener todos los roles
    public function index()
    {
        try {
            $roles = $this->rolModel->getAll();

            if ($roles === false) {
                Response::error('Error al obtener roles', 500);
                return;
            }

            Response::success($roles, 'Roles obtenidos correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener Roles: ', $error->getMessage(), 500);
        }
    }

    //GET /roles/{id} - obtener rol por ID
    public function show($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de cliente inválido');
            return;
        }

        try {
            $rol = $this->rolModel->getById($id);

            if (!$rol) {
                Response::notFound('Rol no encontrado');
                return;
            }

            $rol['total_usuarios'] = $this->rolModel->getUserCount($id);
            $rol['can_delete'] = !$this->rolModel->hasUsers($id);

            Response::success($rol, 'Rol obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener rol' . $error->getMessage(), 500);
        }
    }

    //POST /roles - Crear un nuevo rol
    public function store()
    {
        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        //Validar campos requeridos
        $this->validateRequerid($data, ['nombre_rol', 'descripcion']);

        //limpiar datos
        $data = $this->sanitizeInput($data);

        //validar datos usando modelo
        $validationErrors = $this->rolModel->validateRolData($data);
        if (!empty($validationErrors)) {
            Response::validationError($validationErrors);
            return;
        }

        //Verificar si un rol ya existe
        if ($this->rolModel->nombreRolExists($data['nombre_rol'])) {
            Response::conflict('El nombre del rol ya está registrado');
            return;
        }

        try {
            $rol = $this->rolModel->create($data);

            if (!$rol) {
                Response::error('Error al crear rol', 500);
                return;
            }

            Response::success($rol, 'Rol creado correctamente', 201);
        } catch (\Exception $error) {
            Response::error('Error al crear Rol', $error->getMessage(), 500);
        }
    }

    //PUT /roles/id - Actualizar Rol
    public function update($id)
    {
        RolMiddleware::adminOnly();

        if (!$this->isValidId($id)) {
            Response::error('ID de rol inválido');
        }

        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        if (!$this->rolModel->getById($id)) {
            Response::notFound('Rol no econtrado');
            return;
        }

        $data = $this->sanitizeInput($data);

        try {
            $rol = $this->rolModel->update($id, $data);

            if (!$rol) {
                Response::error('Error al actualizar rol', 500);
                return;
            }

            Response::success($rol, 'Rol actualizado correctamente');

        } catch (\Exception $error) {
            Response::error('Error al actualizar Rol: ', $error->getMessage(), 500);
        }
    }

    //DELETE /clientes/id - Eliminar cliente
    public function delete($id)
    {
        RolMiddleware::adminOnly();

        if (!$this->isValidId($id)) {
            Response::error('ID de rol inválido');
            return;
        }

        //verificar si el rol existe
        if (!$this->rolModel->exists($id)) {
            Response::notFound('Rol no econtrado');
            return;
        }

        try {
            $result = $this->rolModel->delete($id);

            if (!$result) {
                Response::error('Error al eliminar rol', 500);
                return;
            }

            Response::success(null, 'Rol eliminado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al eliminar rol: ', $error->getMessage(),500);
        }
    }

    //GET /roles/{id}/usuarios - obtener conteo de usuarios del rol
    public function getUserCount($id)
    {
        try {
            RolMiddleware::adminOnly();

            if (!$this->isValidId($id)) {
                Response::error('ID de rol inválido');
                return;
            }

            if (!$this->rolModel->exists($id)) {
                Response::notFound('Rol no econtrado');
                return;
            }

            $userCount = $this->rolModel->getUserCount($id);
            Response::success(['total_usuarios'  => $userCount], 'Conteo de usuarios obtenidos');
        } catch (\Exception $error) {
            Response::error('Error al obtener conteo de usuarios: ', $error->getMessage(), 500);
        }
    }

    //GET /roles/buscar/{termino} - Buscar roles
    public function search($searchTerm)
    {
        try {
            RolMiddleware::adminOnly();

            if (empty($searchTerm) || strlen($searchTerm) < 2) {
                Response::error('Término de búsqueda debe tener al menos 2 caracteres');
                return;
            }

            $roles = $this->rolModel->search($searchTerm);
            Response::success($roles, 'Búsqueda completada');
        } catch (\Exception $error) {
            Response::error('Error en la búsqueda: ', $error->getMessage(), 500);
        }
    }

    //GET /roles/estadisticas/conteo - obtener roles con conteo de usuarios
    public function stats()
    {
        try {
            RolMiddleware::adminOnly();

            $roles = $this->rolModel->getAllWithUserCount();
            Response::success($roles, 'Estadisticas de roles obtenidas');
        } catch (\Exception $error) {
            Response::error('Error al obtener estadísticas: ', $error->getMessage(), 500);
        }
    }

    //GET /roles/sistema/lista - obtener lista de roles del sistema
    public function systemRoles()
    {
        try {
            RolMiddleware::anyAuthenticated();

            $systemRoles = $this->rolModel->getSystemRoles();
            Response::success($systemRoles, 'Roles del sistema obtenidos');
        } catch (\Exception $error) {
            Response::error('Error al obtener roles del sistema', $error->getMessage(), 500);
        }

    }
}