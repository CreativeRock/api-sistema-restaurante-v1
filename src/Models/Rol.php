<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Rol
{
    private $dataBase;

    // Roles del sistema que no se pueden modificar/eliminar
    private $systemRoles = ['Admin', 'Gerente', 'Mesero', 'Recepcionista', 'Cocinero', 'Bartender'];

    public function __construct()
    {
        $this->dataBase = Database::getConnection();
    }

    // Obtener todos los roles
    public function getAll()
    {
        $sqlQuery = "SELECT id_rol, nombre_rol, descripcion FROM roles ORDER BY id_rol ASC";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->execute();

        return $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener rol por ID
    public function getById($id)
    {
        $sqlQuery = "SELECT id_rol, nombre_rol, descripcion FROM roles WHERE id_rol = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetch(PDO::FETCH_ASSOC);
    }

    // Crear un nuevo rol
    public function create($data)
    {
        $sqlQuery = "INSERT INTO roles (nombre_rol, descripcion) VALUES (:nombre_rol, :descripcion)";
        $queryStatement = $this->dataBase->prepare($sqlQuery);

        $queryStatement->bindParam(':nombre_rol', $data['nombre_rol']);
        $queryStatement->bindParam(':descripcion', $data['descripcion']);

        if ($queryStatement->execute()) {
            return $this->getById($this->dataBase->lastInsertId());
        }

        return false;
    }

    // Actualizar Rol
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['nombre_rol'])) {
            $fields[] = 'nombre_rol = :nombre_rol';
            $params[':nombre_rol'] = $data['nombre_rol'];
        }

        if (isset($data['descripcion'])) {
            $fields[] = 'descripcion = :descripcion';
            $params[':descripcion'] = $data['descripcion'];
        }

        if (empty($fields)) {
            return false; // No hay campos para actualizar
        }

        $sqlQuery = "UPDATE roles SET " . implode(', ', $fields) . " WHERE id_rol = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        if ($queryStatement->execute()) {
            return $this->getById($id);
        }

        return false;
    }

    // Eliminar rol
    public function delete($id)
    {
        // Verificar si es rol de sistema
        if ($this->isSystemRole($id)) {
            throw new \Exception('No se pueden eliminar roles del sistema');
        }

        // Verificar si tiene usuarios asociados
        if ($this->hasUsers($id)) {
            throw new \Exception('No se puede eliminar el rol porque tiene usuarios asociados');
        }

        $sqlQuery = "DELETE FROM roles WHERE id_rol = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);

        return $queryStatement->execute();
    }

    // Verificar si nombre rol existe
    public function nombreRolExists($nombreRol, $excludeId = null)
    {
        $sqlQuery = "SELECT id_rol FROM roles WHERE LOWER(nombre_rol) = LOWER(:nombre_rol)";

        if ($excludeId) {
            $sqlQuery .= " AND id_rol != :exclude_id";
        }

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':nombre_rol', $nombreRol);

        if ($excludeId) {
            $queryStatement->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $queryStatement->execute();
        return $queryStatement->fetch() !== false;
    }

    // Verificar si rol tiene usuarios asociados
    public function hasUsers($id)
    {
        $sqlQuery = "SELECT COUNT(*) as count FROM usuarios WHERE id_rol = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();

        $result = $queryStatement->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    // Contar usuarios del rol
    public function getUserCount($id)
    {
        $sqlQuery = "SELECT COUNT(*) as count FROM usuarios WHERE id_rol = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();

        $result = $queryStatement->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    // Buscar roles
    public function search($searchTerm)
    {
        $sqlQuery = "SELECT id_rol, nombre_rol, descripcion FROM roles WHERE nombre_rol LIKE :search OR descripcion LIKE :search ORDER BY nombre_rol ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $searchParam = '%' . $searchTerm . '%';
        $queryStatement->bindParam(':search', $searchParam);
        $queryStatement->execute();

        return $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    // Verificar si es rol de sistema
    public function isSystemRole($id)
    {
        $rol = $this->getById($id);
        if (!$rol) {
            return false;
        }

        return in_array($rol['nombre_rol'], $this->systemRoles);
    }

    // Obtener roles del sistema
    public function getSystemRoles()
    {
        return $this->systemRoles;
    }

    // Validar datos del rol
    public function validateRolData($data, $excludeId = null)
    {
        $errors = [];

        // Validar nombre_rol
        if (!isset($data['nombre_rol']) || empty(trim($data['nombre_rol']))) {
            $errors['nombre_rol'] = 'El nombre del rol es requerido';
        } else {
            $nombreRol = trim($data['nombre_rol']);

            if (strlen($nombreRol) < 3) {
                $errors['nombre_rol'] = 'El nombre del rol debe tener al menos 3 caracteres';
            } elseif (strlen($nombreRol) > 50) {
                $errors['nombre_rol'] = 'El nombre del rol no puede exceder 50 caracteres';
            } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombreRol)) {
                $errors['nombre_rol'] = 'El nombre del rol solo puede contener letras y espacios';
            } elseif ($this->nombreRolExists($nombreRol, $excludeId)) {
                $errors['nombre_rol'] = 'El nombre del rol ya está registrado';
            }
        }

        // Validar descripcion
        if (!isset($data['descripcion']) || empty(trim($data['descripcion']))) {
            $errors['descripcion'] = 'La descripción es requerida';
        } else {
            $descripcion = trim($data['descripcion']);

            if (strlen($descripcion) < 10) {
                $errors['descripcion'] = 'La descripción debe tener al menos 10 caracteres';
            } elseif (strlen($descripcion) > 255) {
                $errors['descripcion'] = 'La descripción no puede exceder 255 caracteres';
            }
        }

        return $errors;
    }

    // Obtener roles con conteo de usuarios
    public function getAllWithUserCount()
    {
        $sqlQuery = "SELECT r.id_rol, r.nombre_rol, r.descripcion, COUNT(u.id_usuario) as total_usuarios FROM roles r LEFT JOIN usuarios u ON r.id_rol = u.id_rol GROUP BY r.id_rol ORDER BY r.nombre_rol ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->execute();

        return $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    // Verificar si un rol existe por ID
    public function exists($id)
    {
        $sqlQuery = "SELECT id_rol FROM roles WHERE id_rol = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetch() !== false;
    }
}
