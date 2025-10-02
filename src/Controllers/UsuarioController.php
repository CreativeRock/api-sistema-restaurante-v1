<?php

namespace App\Controllers;

use App\Models\Usuario;
use App\Models\Rol;
use App\Utils\Response;
use RolMiddleware;

class UsuarioController extends BaseController
{
    private $usuarioModel;
    private $rolModel;

    public function __construct()
    {
        $this->usuarioModel = new Usuario();
        $this->rolModel = new Rol();
    }

    // GET /usuarios - obtener todos los usuarios
    public function index()
    {
        try {
            //TODO //Agregar acceso
            //Solo admin y gerente pueden ver usuarios
            // RolMiddleware::adminAndGerente();

            $usuarios = $this->usuarioModel->getAll();
            Response::success($usuarios, 'Usuarios obtenidos correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener usuarios: ', $error->getMessage(), 500);
        }
    }

    // GET /usuarios/{id} - obtener usuarios por ID
    public function show($id)
    {
        try {
            $usuario = $this->usuarioModel->getById($id);

            if (!$usuario) {
                Response::notFound('Usuario no econtrado');
                return;
            }

            Response::success($usuario, 'Usuario obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener usuario: ', $error->getMessage(), 500);
        }
    }

    //POST /usuarios - Crear nuevo usuario
    public function store()
    {
        try {
            //TODO //Agregar acceso
            //Solo admin y gerente pueden ver usuarios
            // RolMiddleware::adminAndGerente();

            $data = $this->getJsonInput();

            if (!$data) {
                Response::error('Datos JSON inválidos');
                return;
            }

            //Validar campos requeridos
            $this->validateRequerid($data, ['id_rol', 'nombre', 'apellido', 'email', 'telefono', 'password']);

            $data = $this->sanitizeInput($data);

            $usuario = $this->usuarioModel->create($data);
            if (!$usuario) {
                Response::error('Error al crear usuario', 500);
                return;
            }

            Response::success($usuario, 'Usuario creado correctamente', 201);
        } catch (\Exception $error) {
            Response::error('Error al crear usuario', $error->getMessage(), 500);
        }
    }

    //PUT /usuario/{id} - Actualizar usuario
    public function update($id)
    {
        try {
            //TODO //Agregar acceso
            //Solo admin y gerente pueden ver usuarios
            // RolMiddleware::adminAndGerente();

            $data = $this->getJsonInput();

            if (!$data) {
                Response::error('Datos JSON inválidos');
                return;
            }

            //Verificar si el usuario existe
            if (!$this->usuarioModel->getById($id)) {
                Response::notFound('Usuario no econtrado');
                return;
            }

            //Validar datos
            $errors = $this->usuarioModel->validateUsuarioData($data, $id);
            if (!empty($errors)) {
                Response::validationError($errors);
                return;
            }

            $usuario = $this->usuarioModel->update($id, $data);
            if (!$usuario) {
                Response::error('Error al actualizar usuario', 500);
                return;
            }

            Response::success($usuario, 'Usuario actualizado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al actualizar usuario', $error->getMessage(), 500);
        }
    }

    //DELETE /usuarios/{id} - Eliminar usuario
    public function delete($id)
    {
        try {
            //TODO //Agregar acceso
            // Solo admin puede eliminar usuarios
            // RolMiddleware::adminOnly();

            if (!$this->isValidId($id)) {
                Response::error('ID de usuario inválido');
                return;
            }

            if ($this->usuarioModel->getById($id)) {
                Response::notFound('Usuario no econtrado');
                return;
            }

            $result = $this->usuarioModel->delete($id);
            if (!$result) {
                Response::error('Error al eliminar usuario', 500);
                return;
            }

            Response::success(null, 'Usuario eliminado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al eliminar usuario: ', $error->getMessage(), 500);
        }
    }

    // GET /usuarios/rol/{id_rol} - Obtener usuarios por rol
    public function getByRole($id_rol)
    {
        try {
            // Solo admin y gerente pueden filtrar por rol
            //RolMiddleware::adminAndGerente();

            if (!$this->isValidId($id_rol)) {
                Response::error('ID de rol inválido');
                return;
            }

            // Verificar si el rol existe
            if (!$this->rolModel->getById($id_rol)) {
                Response::notFound('Rol no encontrado');
                return;
            }

            // Método que necesitarías agregar al modelo Usuario
            $usuarios = $this->usuarioModel->getByRoleId($id_rol);
            Response::success($usuarios, 'Usuarios del rol obtenidos correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener usuarios por rol: ' . $error->getMessage(), 500);
        }
    }

    // GET /usuarios/buscar/{termino} - Buscar usuarios
    public function search($searchTerm)
    {
        try {
            // Solo admin y gerente pueden buscar usuarios
            // RolMiddleware::adminAndGerente();

            if (empty($searchTerm) || strlen($searchTerm) < 2) {
                Response::error('Término de búsqueda debe tener al menos 2 caracteres');
                return;
            }

            // Método que necesitarías agregar al modelo Usuario
            $usuarios = $this->usuarioModel->search($searchTerm);
            Response::success($usuarios, 'Búsqueda completada');
        } catch (\Exception $error) {
            Response::error('Error en la búsqueda: ' . $error->getMessage(), 500);
        }
    }

    // GET /usuarios/estadisticas/conteo - Estadísticas de usuarios
    public function stats()
    {
        try {
            // Solo admin y gerente pueden ver estadísticas
            // RolMiddleware::adminAndGerente();

            // Método que necesitarías agregar al modelo Usuario
            $stats = $this->usuarioModel->getStats();
            Response::success($stats, 'Estadísticas de usuarios obtenidas');
        } catch (\Exception $error) {
            Response::error('Error al obtener estadísticas: ' . $error->getMessage(), 500);
        }
    }

    // PUT /usuarios/{id}/cambiar-password - Cambiar contraseña
    public function changePassword($id)
    {
        try {
            // $this->checkUserAccess($id);

            $data = $this->getJsonInput();
            if (!$data || !isset($data['password_actual']) || !isset($data['password_nuevo'])) {
                Response::error('Datos de contraseña requeridos');
                return;
            }

            // Método que necesitarías agregar al modelo Usuario
            $result = $this->usuarioModel->changePassword($id, $data['password_actual'], $data['password_nuevo']);

            if ($result) {
                Response::success(null, 'Contraseña cambiada correctamente');
            } else {
                Response::error('Error al cambiar contraseña. Verifique la contraseña actual.');
            }
        } catch (\Exception $error) {
            Response::error('Error al cambiar contraseña: ' . $error->getMessage(), 500);
        }
    }


    // GET /usuarios/{id}/perfil - Obtener perfil completo
    public function profile($id)
    {
        try {
            // $this->checkUserAccess($id);

            $usuario = $this->usuarioModel->getById($id);
            if (!$usuario) {
                Response::notFound('Usuario no encontrado');
                return;
            }

            // Agregar información adicional del perfil si es necesario
            $perfil = [
                'usuario' => $usuario,
                'fecha_ultimo_acceso' => date('Y-m-d H:i:s'), // Ejemplo
                'estado' => 'activo'
            ];

            Response::success($perfil, 'Perfil obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener perfil: ' . $error->getMessage(), 500);
        }
    }

    // Método auxiliar para verificar acceso al usuario
    // private function checkUserAccess($userId)
    // {
    //     $currentUser = AuthMiddleware::getCurrentUser();

    //     // Admin y gerente pueden acceder a cualquier usuario
    //     if (in_array($currentUser['rol'], ['Admin', 'Gerente'])) {
    //         return true;
    //     }

    //     // Usuario normal solo puede acceder a su propio perfil
    //     if ($currentUser['id'] != $userId) {
    //         Response::error('No tiene permisos para acceder a este usuario', 403);
    //         exit;
    //     }

    //     return true;
    // }
}
