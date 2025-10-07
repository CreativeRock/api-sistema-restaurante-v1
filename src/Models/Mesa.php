<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Mesa
{
    private $dataBase;

    const ESTADOS_VALIDOS = ['disponible', 'reservada', 'fuera_servicio'];
    const TIPOS_VALIDOS = ['Standard', 'Premium', 'Vip'];

    public function __construct()
    {
        $this->dataBase = Database::getConnection();
    }

    public function getAll()
    {
        $sqlQuery = "SELECT id_mesa, numero_mesa, nombre_mesa, caracteristicas, capacidad, ubicacion, estado, tipo FROM mesas ORDER BY id_mesa DESC";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    public function getById($id)
    {
        $sqlQuery = "SELECT id_mesa, numero_mesa, nombre_mesa, caracteristicas, capacidad, ubicacion, estado, tipo FROM mesas WHERE id_mesa = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetch();
    }

    public function create($data)
    {
        $sqlQuery = "INSERT INTO mesas ( id_mesa, numero_mesa, nombre_mesa, caracteristicas, capacidad, ubicacion, estado, tipo) VALUES VALUES (:numero_mesa, :nombre_mesa, :caracteristicas, :capacidad, :ubicacion, :estado, :tipo)";

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        $queryStatement->bindParam(':numero_mesa', $data['numero_mesa']);
        $queryStatement->bindParam(':nombre_mesa', $data['nombre_mesa']);
        $queryStatement->bindParam(':caracteristicas', $data['caracteristicas']);
        $queryStatement->bindParam(':capacidad', $data['capacidad'], PDO::PARAM_INT);
        $queryStatement->bindParam(':ubicacion', $data['ubicacion']);
        $queryStatement->bindParam(':estado', $data['estado']);
        $queryStatement->bindParam(':tipo', $data['tipo']);

        if ($queryStatement->execute()) {
            return $this->getById($this->dataBase->lastInsertId());
        }

        return false;
    }

    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['numero_mesa'])) {
            $fields[] = "numero_mesa = :numero_mesa";
            $params[':numero_mesa'] = $data['numero_mesa'];
        }

        if (isset($data['nombre_mesa'])) {
            $fields[] = "nombre_mesa = :nombre_mesa";
            $params[':nombre_mesa'] = $data['nombre_mesa'];
        }

        if (isset($data['caracteristicas'])) {
            $fields[] = "caracteristicas = :caracteristicas";
            $params[':caracteristicas'] = $data['caracteristicas'];
        }

        if (isset($data['capacidad'])) {
            $fields[] = "capacidad = :capacidad";
            $params[':capacidad'] = $data['capacidad'];
        }

        if (isset($data['ubicacion'])) {
            $fields[] = "ubicacion = :ubicacion";
            $params[':ubicacion'] = $data['ubicacion'];
        }

        if (isset($data['estado'])) {
            $fields[] = "estado = :estado";
            $params[':estado'] = $data['estado'];
        }

        if (isset($data['tipo'])) {
            $fields[] = "tipo = :tipo";
            $params[':tipo'] = $data['tipo'];
        }

        if (empty($fields)) {
            return $this->getById($id);
        }

        $sqlQuery = "UPDATE mesa SET " . implode(', ', $fields) . " WHERE id_mesa = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        if ($queryStatement->execute()) {
            return $this->getById($id);
        }

        return false;
    }

    //Eliminar mesa
    public function delete($id)
    {
        $sqlQuery = "DELETE FROM mesas WHERE id_mesa = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);

        return $queryStatement->execute();
    }

    //Verificar si existe un número de mesa
    public function numeroMesaExists($numeroMesa, $excludeId = null)
    {
        $sqlQuery = "SELECT id_mesa FROM mesas WHERE numero_mesa = :numero_mesa";

        if ($excludeId) {
            $sqlQuery .= " AND id_mesa != :exclude_id";
        }

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':numero_mesa', $numeroMesa);

        if ($excludeId) {
            $queryStatement->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $queryStatement->execute();
        return $queryStatement->fetch() !== false;
    }

    //Validar estado ENUM
    public static function isValidEstado($estado)
    {
        return in_array($estado, self::ESTADOS_VALIDOS);
    }

    //Validad tipo ENUM
    public static function isValidTipo($tipo)
    {
        return in_array($tipo, self::TIPOS_VALIDOS);
    }

    //Obtener estados válidos
    public static function getEstadosValidos()
    {
        return self::ESTADOS_VALIDOS;
    }

    //Obtener tipos válidos
    public static function getTiposValidos()
    {
        return self::TIPOS_VALIDOS;
    }

    //Obtener mesas disponibles por capacidad, fecha y hora
    public function getAvailableByCapacity($capacidad, $fecha, $hora)
    {
        $sqlQuery = "SELECT m.id_mesa, m.numero_mesa, m.nombre_mesa, m.caracteristicas,
                        m.capacidad, m.ubicacion, m.estado, m.tipo
                 FROM mesas m
                 WHERE m.capacidad >= :capacidad
                 AND m.estado = 'disponible'
                 AND m.id_mesa NOT IN (
                     SELECT r.id_mesa
                     FROM reservas r
                     WHERE r.fecha_reserva = :fecha
                     AND r.hora_reserva = :hora
                     AND r.estado IN ('pendiente', 'confirmada')
                 )
                 ORDER BY m.capacidad ASC, m.tipo DESC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':capacidad', $capacidad, PDO::PARAM_INT);
        $queryStatement->bindParam(':fecha', $fecha);
        $queryStatement->bindParam(':hora', $hora);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }
    //Obtener mesas por tipo
    public function getByType($tipo)
    {
        $sqlQuery = "SELECT id_mesa, numero_mesa, nombre_mesa, caracteristicas, capacidad, ubicacion, estado, tipo FROM mesas WHERE tipo = :tipo ORDER BY numero_mesa ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':tipo', $tipo);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    //Obtener mesas por estado
    public function getByStatus($estado)
    {
        $sqlQuery = "SELECT id_mesa, numero_mesa, nombre_mesa, caracteristicas, capacidad, ubicacion, estado, tipo FROM mesas WHERE estado = :estado ORDER BY numero_mesa ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':estado', $estado);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    //Cambiar estado de mesa
    public function changeStatus($id, $estado)
    {
        $sqlQuery = "UPDATE mesas SET estado = :estado WHERE id_mesa = :id";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':estado', $estado);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);

        if ($queryStatement->execute()) {
            return $this->getById($id);
        }

        return false;
    }

    //Obtener mesas disponibles con filtros avanzados
    public function getAvailableWithFilters($capacidad = null, $tipo = null, $ubicacion = null)
    {
        $sqlQuery = "SELECT id_mesa, numero_mesa, nombre_mesa, caracteristicas, capacidad, ubicacion, estado, tipo FROM mesas WHERE estado = 'disponible'";
        $params = [];

        if ($capacidad) {
            $sqlQuery .= " AND capacidad >= :capacidad";
            $params[':capacidad'] = $capacidad;
        }

        if ($tipo) {
            $sqlQuery .= " AND tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        if ($ubicacion) {
            $sqlQuery .= " AND ubicacion LIKE :ubicacion";
            $params[':ubicacion'] = "%$ubicacion%";
        }

        $sqlQuery .= " ORDER BY tipo DESC, capacidad ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        $queryStatement->execute();
        return $queryStatement->fetchAll();
    }
}
