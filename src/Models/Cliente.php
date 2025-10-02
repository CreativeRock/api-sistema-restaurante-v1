<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Cliente
{
    private $dataBase;

    public function __construct()
    {
        $this->dataBase = Database::getConnection();
    }

    //Obtener todos los clientes
    public function getAll()
    {
        $sqlQuery = "SELECT id_cliente, nombre, apellido, email, telefono, preferencias, fecha_registro, fecha_actualizacion FROM clientes ORDER BY id_cliente DESC";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    //Obtener cliente por ID
    public function getById($id)
    {
        $sqlQuery = "SELECT id_cliente, nombre, apellido, email, telefono, preferencias, fecha_registro, fecha_actualizacion FROM clientes WHERE id_cliente = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetch();
    }

    //Crear un nuevo cliente
    public function create($data)
    {
        $sqlQuery = "INSERT INTO clientes (nombre, apellido, email, telefono, preferencias, password) VALUES (:nombre, :apellido, :email, :telefono, :preferencias, :password)";
        $queryStatement = $this->dataBase->prepare($sqlQuery);

        //Hashear la contraseña
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        $queryStatement->bindParam(':nombre', $data['nombre']);
        $queryStatement->bindParam(':apellido', $data['apellido']);
        $queryStatement->bindParam(':email', $data['email']);
        $queryStatement->bindParam(':telefono', $data['telefono']);
        $queryStatement->bindParam(':preferencias', $data['preferencias']);
        $queryStatement->bindParam(':password', $hashedPassword);

        if ($queryStatement->execute()) {
            return $this->getById($this->dataBase->lastInsertId());
        }

        return false;
    }

    //Actualizar cliente
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

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

        if (isset($data['preferencias'])) {
            $fields[] = "preferencias = :preferencias";
            $params[':preferencias'] = $data['preferencias'];
        }

        if (isset($data['password'])) {
            $fields[] = "password = :password";
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $fields[] = "fecha_actualizacion = NOW()";

        $sqlQuery = "UPDATE clientes SET " . implode(', ', $fields) . " WHERE id_cliente = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        if ($queryStatement->execute()) {
            return $this->getById($id);
        }

        return false;
    }

    //Eliminar cliente
    public function delete($id)
    {
        $sqlQuery = "DELETE FROM clientes WHERE id_cliente = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);

        return $queryStatement->execute();
    }

    //Verificar si existe un email
    public function emailExists($email, $excludeId = null)
    {
        $sqlQuery = "SELECT id_cliente FROM clientes WHERE email = :email";

        if ($excludeId) {
            $sqlQuery .= " AND id_cliente != :exclude_id";
        }

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':email', $email);

        if ($excludeId) {
            $queryStatement->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $queryStatement->execute();
        return $queryStatement->fetch() !== false;
    }

    //Autenticar cliente
    public function autenticate($email, $password)
    {
        $sqlQuery = "SELECT id_cliente, nombre, apellido, email, password FROM clientes WHERE email = :email";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':email', $email);
        $queryStatement->execute();

        $cliente = $queryStatement->fetch();

        if ($cliente && password_verify($password, $cliente['password'])) {
            unset($cliente['password']);
            return $cliente;
        }

        return false;
    }
}
