<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class HistorialReserva
{
    private $dataBase;

    public function __construct()
    {
        $this->dataBase = Database::getConnection();
    }

    // Crear registro en historial
    public function create($data)
    {
        $sqlQuery = "INSERT INTO historial_reservas (
            id_reserva,
            id_usuario,
            accion,
            detalle
        ) VALUES (
            :id_reserva,
            :id_usuario,
            :accion,
            :detalle
        )";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id_reserva', $data['id_reserva'], PDO::PARAM_INT);
        $queryStatement->bindParam(':id_usuario', $data['id_usuario'], PDO::PARAM_INT);
        $queryStatement->bindParam(':accion', $data['accion']);
        $queryStatement->bindParam(':detalle', $data['detalle']);

        return $queryStatement->execute();
    }

    // Obtener historial por reserva
    public function getByReserva($id_reserva)
    {
        $sqlQuery = "SELECT
            h.id_historial,
            h.id_reserva,
            h.id_usuario,
            h.accion,
            h.fecha_accion,
            h.detalle,
            CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
            u.rol as rol_usuario
        FROM historial_reservas h
        LEFT JOIN usuarios u ON h.id_usuario = u.id_usuario
        WHERE h.id_reserva = :id_reserva
        ORDER BY h.fecha_accion DESC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id_reserva', $id_reserva, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    // Obtener todo el historial con filtros
    public function getAll($filters = [])
    {
        $sqlQuery = "SELECT
            h.id_historial,
            h.id_reserva,
            h.id_usuario,
            h.accion,
            h.fecha_accion,
            h.detalle,
            CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
            u.rol as rol_usuario,
            r.codigo_reserva,
            r.fecha_reserva,
            r.hora_reserva,
            CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
            m.numero_mesa
        FROM historial_reservas h
        LEFT JOIN usuarios u ON h.id_usuario = u.id_usuario
        LEFT JOIN reservas r ON h.id_reserva = r.id_reserva
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN mesas m ON r.id_mesa = m.id_mesa";

        $conditions = [];
        $params = [];

        // Filtros opcionales
        if (!empty($filters['accion'])) {
            $conditions[] = "h.accion = :accion";
            $params[':accion'] = $filters['accion'];
        }

        if (!empty($filters['fecha_desde'])) {
            $conditions[] = "DATE(h.fecha_accion) >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }

        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = "DATE(h.fecha_accion) <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }

        if (!empty($filters['usuario'])) {
            $conditions[] = "h.id_usuario = :usuario";
            $params[':usuario'] = $filters['usuario'];
        }

        if (!empty($filters['reserva'])) {
            $conditions[] = "h.id_reserva = :reserva";
            $params[':reserva'] = $filters['reserva'];
        }

        if (!empty($conditions)) {
            $sqlQuery .= " WHERE " . implode(' AND ', $conditions);
        }

        $sqlQuery .= " ORDER BY h.fecha_accion DESC";

        // Paginación
        if (!empty($filters['limit'])) {
            $sqlQuery .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];
        }

        if (!empty($filters['offset'])) {
            $sqlQuery .= " OFFSET :offset";
            $params[':offset'] = (int)$filters['offset'];
        }

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $queryStatement->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $queryStatement->bindValue($key, $value);
            }
        }

        $queryStatement->execute();
        return $queryStatement->fetchAll();
    }

    // Métricas de acciones por período
    public function getActionMetrics($fecha_desde = null, $fecha_hasta = null)
    {
        $sqlQuery = "SELECT
            accion,
            COUNT(*) as total,
            DATE(fecha_accion) as fecha
        FROM historial_reservas";

        $conditions = [];
        $params = [];

        if ($fecha_desde) {
            $conditions[] = "DATE(fecha_accion) >= :fecha_desde";
            $params[':fecha_desde'] = $fecha_desde;
        }

        if ($fecha_hasta) {
            $conditions[] = "DATE(fecha_accion) <= :fecha_hasta";
            $params[':fecha_hasta'] = $fecha_hasta;
        }

        if (!empty($conditions)) {
            $sqlQuery .= " WHERE " . implode(' AND ', $conditions);
        }

        $sqlQuery .= " GROUP BY accion, DATE(fecha_accion) ORDER BY fecha DESC, accion";

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        $queryStatement->execute();
        return $queryStatement->fetchAll();
    }

    // Resumen de acciones
    public function getActionSummary($fecha_desde = null, $fecha_hasta = null)
    {
        $sqlQuery = "SELECT
            accion,
            COUNT(*) as total
        FROM historial_reservas";

        $conditions = [];
        $params = [];

        if ($fecha_desde) {
            $conditions[] = "DATE(fecha_accion) >= :fecha_desde";
            $params[':fecha_desde'] = $fecha_desde;
        }

        if ($fecha_hasta) {
            $conditions[] = "DATE(fecha_accion) <= :fecha_hasta";
            $params[':fecha_hasta'] = $fecha_hasta;
        }

        if (!empty($conditions)) {
            $sqlQuery .= " WHERE " . implode(' AND ', $conditions);
        }

        $sqlQuery .= " GROUP BY accion ORDER BY total DESC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        $queryStatement->execute();
        return $queryStatement->fetchAll();
    }

    // Actividad por usuario
    public function getUserActivity($fecha_desde = null, $fecha_hasta = null)
    {
        $sqlQuery = "SELECT
            h.id_usuario,
            CONCAT(u.nombre, ' ', u.apellido) as nombre_usuario,
            u.rol,
            COUNT(*) as total_acciones,
            COUNT(CASE WHEN h.accion = 'creacion' THEN 1 END) as creaciones,
            COUNT(CASE WHEN h.accion = 'modificacion' THEN 1 END) as modificaciones,
            COUNT(CASE WHEN h.accion = 'cancelacion' THEN 1 END) as cancelaciones
        FROM historial_reservas h
        LEFT JOIN usuarios u ON h.id_usuario = u.id_usuario";

        $conditions = [];
        $params = [];

        if ($fecha_desde) {
            $conditions[] = "DATE(h.fecha_accion) >= :fecha_desde";
            $params[':fecha_desde'] = $fecha_desde;
        }

        if ($fecha_hasta) {
            $conditions[] = "DATE(h.fecha_accion) <= :fecha_hasta";
            $params[':fecha_hasta'] = $fecha_hasta;
        }

        if (!empty($conditions)) {
            $sqlQuery .= " WHERE " . implode(' AND ', $conditions);
        }

        $sqlQuery .= " GROUP BY h.id_usuario ORDER BY total_acciones DESC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        $queryStatement->execute();
        return $queryStatement->fetchAll();
    }

    // Reservas más modificadas
    public function getMostModifiedReservations($limit = 10)
    {
        $sqlQuery = "SELECT
            h.id_reserva,
            r.codigo_reserva,
            r.fecha_reserva,
            r.hora_reserva,
            CONCAT(c.nombre, ' ', c.apellido) as nombre_cliente,
            m.numero_mesa,
            COUNT(*) as total_modificaciones
        FROM historial_reservas h
        LEFT JOIN reservas r ON h.id_reserva = r.id_reserva
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN mesas m ON r.id_mesa = m.id_mesa
        WHERE h.accion IN ('modificacion', 'cancelacion')
        GROUP BY h.id_reserva
        ORDER BY total_modificaciones DESC
        LIMIT :limit";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':limit', $limit, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }
}
