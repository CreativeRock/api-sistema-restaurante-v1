<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Usuario
{
    private $dataBase;

    public function __construct()
    {
        $this->dataBase = Database::getConnection();
    }

    //Obtener todos los usuarios
    public function getAll()
    {
        $sqlQuery = "SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.fecha_creacion, u.fecha_actualizacion, r.nombre_rol, r.id_rol FROM usuarios u INNER JOIN roles r ON u.id_rol = r.id_rol ORDER BY u.id_usuario DESC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->execute();
        return $queryStatement->fetchAll();
    }

    //Obtener usuario por ID
    public function getById($id)
    {
        $sqlQuery = "SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.fecha_creacion, u.fecha_actualizacion, r.nombre_rol, r.id_rol FROM usuarios u INNER JOIN roles r ON u.id_rol = r.id_rol WHERE u.id_usuario = :id";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();
        return $queryStatement->fetch();
    }

    //Crear un nuevo usuario
    public function create($data)
    {
        $sqlQuery = "INSERT INTO usuarios (id_rol, nombre, apellido, email, telefono, password) VALUES (:id_rol, :nombre, :apellido, :email, :telefono, :password)";

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        $queryStatement->bindParam(':id_rol', $data['id_rol'], PDO::PARAM_INT);
        $queryStatement->bindParam(':nombre', $data['nombre']);
        $queryStatement->bindParam(':apellido', $data['apellido']);
        $queryStatement->bindParam(':email', $data['email']);
        $queryStatement->bindParam(':telefono', $data['telefono']);
        $queryStatement->bindParam(':password', $hashedPassword);

        if ($queryStatement->execute()) {
            return $this->getById($this->dataBase->lastInsertId());
        }

        return;
    }

    //Actualizar usuario
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['id_rol'])) {
            $fields[] = "id_rol = :id_rol";
            $params[':id_rol'] = $data['id_rol'];
        }
        if (isset($data['nombre'])) {
            $fields[] = "nombre = :nombre";
            $params[':nombre'] = $data['nombre'];
        }
        if (isset($data['apellido'])) {
            $fields[] = "apellido = :apellido";
            $params[':apellido'] = $data['apellido'];
        }
        if (isset($data['email'])) {
            $fields[] = "email = :email";
            $params[':email'] = $data['email'];
        }
        if (isset($data['telefono'])) {
            $fields[] = "telefono = :telefono";
            $params[':telefono'] = $data['telefono'];
        }
        if (isset($data['password'])) {
            $fields[] = "password = :password";
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $fields[] = "fecha_actualizacion = NOW()";

        $sqlQuery = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id_usuario = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        if ($queryStatement->execute()) {
            return $this->getById($id);
        }
        return false;
    }

    //Eliminar usuario
    public function delete($id)
    {
        $sqlQuery = "DELETE FROM usuarios WHERE id_usuario = :id";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        return $queryStatement->execute();
    }

    // Verificar si email existe
    public function emailExists($email, $excludeId = null)
    {
        $sqlQuery = "SELECT id_usuario FROM usuarios WHERE email = :email";
        if ($excludeId) {
            $sqlQuery .= " AND id_usuario != :exclude_id";
        }
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':email', $email);
        if ($excludeId) {
            $queryStatement->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $queryStatement->execute();
        return $queryStatement->fetch() !== false;
    }

    // Autenticar usuario
    public function authenticate($email, $password)
    {
        $sqlQuery = "SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.password, r.nombre_rol, r.id_rol FROM usuarios u INNER JOIN roles r ON u.id_rol = r.id_rol WHERE u.email = :email";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':email', $email);
        $queryStatement->execute();
        $usuario = $queryStatement->fetch();

        if ($usuario && password_verify($password, $usuario['password'])) {
            unset($usuario['password']);
            return $usuario;
        }
        return false;
    }

    // Validar datos de usuario
    public function validateUsuarioData($data, $excludeId = null)
    {
        $errors = [];

        // Validar id_rol
        if (!isset($data['id_rol']) || empty($data['id_rol'])) {
            $errors[] = 'El rol es requerido';
        } else {
            $rolModel = new Rol();
            if (!$rolModel->getById($data['id_rol'])) {
                $errors[] = 'El rol especificado no existe';
            }
        }

        // Validar nombre
        if (!isset($data['nombre']) || empty(trim($data['nombre']))) {
            $errors[] = 'El nombre es requerido';
        } elseif (strlen(trim($data['nombre'])) < 2) {
            $errors[] = 'El nombre debe tener al menos 2 caracteres';
        }

        // Validar apellido
        if (!isset($data['apellido']) || empty(trim($data['apellido']))) {
            $errors[] = 'El apellido es requerido';
        } elseif (strlen(trim($data['apellido'])) < 2) {
            $errors[] = 'El apellido debe tener al menos 2 caracteres';
        }

        // Validar email
        if (!isset($data['email']) || empty(trim($data['email']))) {
            $errors[] = 'El email es requerido';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Formato de email inválido';
        } elseif ($this->emailExists($data['email'], $excludeId)) {
            $errors[] = 'El email ya está registrado';
        }

        // Validar teléfono
        if (!isset($data['telefono']) || empty(trim($data['telefono']))) {
            $errors[] = 'El teléfono es requerido';
        } elseif (!preg_match('/^[0-9]{8}$/', $data['telefono'])) {
            $errors[] = 'El teléfono debe tener exactamente 8 dígitos';
        }

        // Validar password (solo para creación)
        if (!isset($data['id_usuario']) && (!isset($data['password']) || strlen($data['password']) < 6)) {
            $errors[] = 'La contraseña es requerida y debe tener al menos 6 caracteres';
        }

        return $errors;
    }

    // Obtener usuarios por ID de rol
    public function getByRoleId($roleId)
    {
        $sqlQuery = "SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.fecha_creacion, u.fecha_actualizacion, r.nombre_rol FROM usuarios u INNER JOIN roles r ON u.id_rol = r.id_rol WHERE u.id_rol = :role_id ORDER BY u.nombre ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    // Buscar usuarios
    public function search($searchTerm)
    {
        $sqlQuery = "SELECT u.id_usuario, u.nombre, u.apellido, u.email, u.telefono, u.fecha_creacion, u.fecha_actualizacion, r.nombre_rol FROM usuarios u INNER JOIN roles r ON u.id_rol = r.id_rol  WHERE u.nombre LIKE :search OR u.apellido LIKE :search OR u.email LIKE :search ORDER BY u.nombre ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $searchParam = '%' . $searchTerm . '%';
        $queryStatement->bindParam(':search', $searchParam);
        $queryStatement->execute();

        return $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener estadísticas
    public function getStats()
    {
        $sqlQuery = "SELECT r.nombre_rol, COUNT(u.id_usuario) as total_usuarios
                FROM roles r
                LEFT JOIN usuarios u ON r.id_rol = u.id_rol
                GROUP BY r.id_rol
                ORDER BY r.nombre_rol ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->execute();

        return $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    // Cambiar contraseña
    public function changePassword($id, $currentPassword, $newPassword)
    {
        // Primero verificar la contraseña actual
        $sqlQuery = "SELECT password FROM usuarios WHERE id_usuario = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();

        $usuario = $queryStatement->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($currentPassword, $usuario['password'])) {
            return false;
        }

        // Actualizar contraseña
        $sqlQuery = "UPDATE usuarios SET password = :password, fecha_actualizacion = NOW() WHERE id_usuario = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $queryStatement->bindParam(':password', $hashedPassword);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);

        return $queryStatement->execute();
    }
}
