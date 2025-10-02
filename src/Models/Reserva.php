<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Reserva
{
    private $dataBase;

    public function __construct()
    {
        $this->dataBase = Database::getConnection();
    }

    // Obtener todas las reservas con filtros
    public function getAll($filters = [])
    {
        $sqlQuery = "SELECT
            r.id_reserva,
            r.codigo_reserva,
            r.id_mesa,
            r.id_cliente,
            r.id_usuario,
            r.fecha_reserva,
            r.hora_reserva,
            r.numero_personas,
            r.estado,
            r.tipo_reserva,
            r.notas,
            r.fecha_creacion,
            r.fecha_actualizacion,
            m.numero_mesa,
            m.capacidad as capacidad_mesa,
            m.ubicacion as ubicacion_mesa,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN CONCAT(c.nombre, ' ', c.apellido)
                WHEN r.id_usuario IS NOT NULL THEN CONCAT(u.nombre, ' ', u.apellido)
                ELSE 'Sin asignar'
            END as nombre_completo,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN c.telefono
                WHEN r.id_usuario IS NOT NULL THEN u.telefono
                ELSE NULL
            END as telefono,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN c.email
                WHEN r.id_usuario IS NOT NULL THEN u.email
                ELSE NULL
            END as email
        FROM reservas r
        LEFT JOIN mesas m ON r.id_mesa = m.id_mesa
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario";

        $conditions = [];
        $params = [];

        // Filtros opcionales
        if (!empty($filters['estado'])) {
            $conditions[] = "r.estado = :estado";
            $params[':estado'] = $filters['estado'];
        }

        if (!empty($filters['fecha_desde'])) {
            $conditions[] = "r.fecha_reserva >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }

        if (!empty($filters['fecha_hasta'])) {
            $conditions[] = "r.fecha_reserva <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }

        if (!empty($filters['mesa'])) {
            $conditions[] = "r.id_mesa = :mesa";
            $params[':mesa'] = $filters['mesa'];
        }

        if (!empty($filters['cliente'])) {
            $conditions[] = "r.id_cliente = :cliente";
            $params[':cliente'] = $filters['cliente'];
        }

        if (!empty($filters['tipo_reserva'])) {
            $conditions[] = "r.tipo_reserva = :tipo_reserva";
            $params[':tipo_reserva'] = $filters['tipo_reserva'];
        }

        // Excluir reservas canceladas por defecto (a menos que se especifique)
        if (!isset($filters['incluir_canceladas']) || !$filters['incluir_canceladas']) {
            $conditions[] = "r.estado != 'cancelada'";
        }

        if (!empty($conditions)) {
            $sqlQuery .= " WHERE " . implode(' AND ', $conditions);
        }

        $sqlQuery .= " ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC";

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

    // Obtener reserva por ID (incluyendo canceladas para historial)
    public function getById($id, $incluirCanceladas = true)
    {
        $sqlQuery = "SELECT
            r.id_reserva,
            r.codigo_reserva,
            r.id_mesa,
            r.id_cliente,
            r.id_usuario,
            r.fecha_reserva,
            r.hora_reserva,
            r.numero_personas,
            r.estado,
            r.tipo_reserva,
            r.notas,
            r.fecha_creacion,
            r.fecha_actualizacion,
            m.numero_mesa,
            m.capacidad as capacidad_mesa,
            m.ubicacion as ubicacion_mesa,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN CONCAT(c.nombre, ' ', c.apellido)
                WHEN r.id_usuario IS NOT NULL THEN CONCAT(u.nombre, ' ', u.apellido)
                ELSE 'Sin asignar'
            END as nombre_completo,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN c.telefono
                WHEN r.id_usuario IS NOT NULL THEN u.telefono
                ELSE NULL
            END as telefono,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN c.email
                WHEN r.id_usuario IS NOT NULL THEN u.email
                ELSE NULL
            END as email
        FROM reservas r
        LEFT JOIN mesas m ON r.id_mesa = m.id_mesa
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
        WHERE r.id_reserva = :id";

        // Solo excluir canceladas si se especifica
        if (!$incluirCanceladas) {
            $sqlQuery .= " AND r.estado != 'cancelada'";
        }

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetch();
    }

    // Crear nueva reserva
    public function create($data)
    {
        $sqlQuery = "INSERT INTO reservas (
            codigo_reserva,
            id_mesa,
            id_cliente,
            id_usuario,
            fecha_reserva,
            hora_reserva,
            numero_personas,
            estado,
            tipo_reserva,
            notas
        ) VALUES (
            :codigo_reserva,
            :id_mesa,
            :id_cliente,
            :id_usuario,
            :fecha_reserva,
            :hora_reserva,
            :numero_personas,
            :estado,
            :tipo_reserva,
            :notas
        )";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':codigo_reserva', $data['codigo_reserva']);
        $queryStatement->bindParam(':id_mesa', $data['id_mesa'], PDO::PARAM_INT);

        // Manejar valores NULL correctamente
        if (isset($data['id_cliente']) && $data['id_cliente'] !== null) {
            $queryStatement->bindParam(':id_cliente', $data['id_cliente'], PDO::PARAM_INT);
        } else {
            $queryStatement->bindValue(':id_cliente', null, PDO::PARAM_NULL);
        }

        if (isset($data['id_usuario']) && $data['id_usuario'] !== null) {
            $queryStatement->bindParam(':id_usuario', $data['id_usuario'], PDO::PARAM_INT);
        } else {
            $queryStatement->bindValue(':id_usuario', null, PDO::PARAM_NULL);
        }

        $queryStatement->bindParam(':fecha_reserva', $data['fecha_reserva']);
        $queryStatement->bindParam(':hora_reserva', $data['hora_reserva']);
        $queryStatement->bindParam(':numero_personas', $data['numero_personas'], PDO::PARAM_INT);
        $queryStatement->bindParam(':estado', $data['estado']);
        $queryStatement->bindParam(':tipo_reserva', $data['tipo_reserva']);

        if (isset($data['notas']) && $data['notas'] !== null) {
            $queryStatement->bindParam(':notas', $data['notas']);
        } else {
            $queryStatement->bindValue(':notas', null, PDO::PARAM_NULL);
        }

        if ($queryStatement->execute()) {
            $id = $this->dataBase->lastInsertId();
            return $this->getById($id);
        }

        return false;
    }

    // Actualizar reserva
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        // Construir dinámicamente la consulta según los campos proporcionados
        if (isset($data['id_mesa'])) {
            $fields[] = "id_mesa = :id_mesa";
            $params[':id_mesa'] = $data['id_mesa'];
        }

        if (isset($data['id_cliente'])) {
            $fields[] = "id_cliente = :id_cliente";
            $params[':id_cliente'] = $data['id_cliente'];
        }

        if (isset($data['id_usuario'])) {
            $fields[] = "id_usuario = :id_usuario";
            $params[':id_usuario'] = $data['id_usuario'];
        }

        if (isset($data['fecha_reserva'])) {
            $fields[] = "fecha_reserva = :fecha_reserva";
            $params[':fecha_reserva'] = $data['fecha_reserva'];
        }

        if (isset($data['hora_reserva'])) {
            $fields[] = "hora_reserva = :hora_reserva";
            $params[':hora_reserva'] = $data['hora_reserva'];
        }

        if (isset($data['numero_personas'])) {
            $fields[] = "numero_personas = :numero_personas";
            $params[':numero_personas'] = $data['numero_personas'];
        }

        if (isset($data['estado'])) {
            $fields[] = "estado = :estado";
            $params[':estado'] = $data['estado'];
        }

        if (isset($data['tipo_reserva'])) {
            $fields[] = "tipo_reserva = :tipo_reserva";
            $params[':tipo_reserva'] = $data['tipo_reserva'];
        }

        if (isset($data['notas'])) {
            $fields[] = "notas = :notas";
            $params[':notas'] = $data['notas'];
        }

        // Siempre actualizar fecha de modificación
        $fields[] = "fecha_actualizacion = CURRENT_TIMESTAMP";

        if (empty($fields)) {
            return false; // No hay campos para actualizar
        }

        $sqlQuery = "UPDATE reservas SET " . implode(', ', $fields) . " WHERE id_reserva = :id";

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            if (in_array($key, [':id', ':id_mesa', ':id_cliente', ':id_usuario', ':numero_personas'])) {
                $queryStatement->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $queryStatement->bindValue($key, $value);
            }
        }

        if ($queryStatement->execute()) {
            return $this->getById($id);
        }

        return false;
    }

    // Cambiar estado de reserva (para cancelaciones y confirmaciones)
    public function changeStatus($id, $estado)
    {
        $sqlQuery = "UPDATE reservas SET
            estado = :estado,
            fecha_actualizacion = CURRENT_TIMESTAMP
        WHERE id_reserva = :id";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->bindParam(':estado', $estado);

        if ($queryStatement->execute()) {
            return $this->getById($id);
        }

        return false;
    }

    // Eliminar reserva (hard delete - solo para administradores)
    public function delete($id)
    {
        $sqlQuery = "DELETE FROM reservas WHERE id_reserva = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);

        return $queryStatement->execute();
    }

    // Verificar disponibilidad de mesa
    public function checkAvailability($id_mesa, $fecha_reserva, $hora_reserva, $exclude_id = null)
    {
        $sqlQuery = "SELECT COUNT(*) as count FROM reservas
        WHERE id_mesa = :id_mesa
        AND fecha_reserva = :fecha_reserva
        AND hora_reserva = :hora_reserva
        AND estado NOT IN ('cancelada')";

        $params = [
            ':id_mesa' => $id_mesa,
            ':fecha_reserva' => $fecha_reserva,
            ':hora_reserva' => $hora_reserva
        ];

        // Excluir una reserva específica (útil para actualizaciones)
        if ($exclude_id !== null) {
            $sqlQuery .= " AND id_reserva != :exclude_id";
            $params[':exclude_id'] = $exclude_id;
        }

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            if (in_array($key, [':id_mesa', ':exclude_id'])) {
                $queryStatement->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $queryStatement->bindValue($key, $value);
            }
        }

        $queryStatement->execute();
        $result = $queryStatement->fetch();

        return $result['count'] == 0;
    }

    // Verificar si existe un código de reserva
    public function codeExists($codigo)
    {
        $sqlQuery = "SELECT COUNT(*) as count FROM reservas WHERE codigo_reserva = :codigo";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':codigo', $codigo);
        $queryStatement->execute();

        $result = $queryStatement->fetch();
        return $result['count'] > 0;
    }

    // Obtener reservas por cliente
    public function getByCliente($id_cliente)
    {
        $sqlQuery = "SELECT
            r.id_reserva,
            r.codigo_reserva,
            r.id_mesa,
            r.fecha_reserva,
            r.hora_reserva,
            r.numero_personas,
            r.estado,
            r.tipo_reserva,
            r.notas,
            r.fecha_creacion,
            r.fecha_actualizacion,
            m.numero_mesa,
            m.capacidad as capacidad_mesa,
            m.ubicacion as ubicacion_mesa
        FROM reservas r
        LEFT JOIN mesas m ON r.id_mesa = m.id_mesa
        WHERE r.id_cliente = :id_cliente
        ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id_cliente', $id_cliente, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    // Obtener reservas por fecha
    public function getByDate($fecha)
    {
        $sqlQuery = "SELECT
            r.id_reserva,
            r.codigo_reserva,
            r.id_mesa,
            r.id_cliente,
            r.id_usuario,
            r.fecha_reserva,
            r.hora_reserva,
            r.numero_personas,
            r.estado,
            r.tipo_reserva,
            r.notas,
            r.fecha_creacion,
            m.numero_mesa,
            m.capacidad as capacidad_mesa,
            m.ubicacion as ubicacion_mesa,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN CONCAT(c.nombre, ' ', c.apellido)
                WHEN r.id_usuario IS NOT NULL THEN CONCAT(u.nombre, ' ', u.apellido)
                ELSE 'Sin asignar'
            END as nombre_completo,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN c.telefono
                WHEN r.id_usuario IS NOT NULL THEN u.telefono
                ELSE NULL
            END as telefono
        FROM reservas r
        LEFT JOIN mesas m ON r.id_mesa = m.id_mesa
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
        WHERE r.fecha_reserva = :fecha
        AND r.estado != 'cancelada'
        ORDER BY r.hora_reserva ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':fecha', $fecha);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    // Obtener reservas por estado
    public function getByStatus($estado)
    {
        $sqlQuery = "SELECT
            r.id_reserva,
            r.codigo_reserva,
            r.id_mesa,
            r.id_cliente,
            r.id_usuario,
            r.fecha_reserva,
            r.hora_reserva,
            r.numero_personas,
            r.estado,
            r.tipo_reserva,
            r.notas,
            r.fecha_creacion,
            m.numero_mesa,
            m.capacidad as capacidad_mesa,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN CONCAT(c.nombre, ' ', c.apellido)
                WHEN r.id_usuario IS NOT NULL THEN CONCAT(u.nombre, ' ', u.apellido)
                ELSE 'Sin asignar'
            END as nombre_completo,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN c.telefono
                WHEN r.id_usuario IS NOT NULL THEN u.telefono
                ELSE NULL
            END as telefono
        FROM reservas r
        LEFT JOIN mesas m ON r.id_mesa = m.id_mesa
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
        WHERE r.estado = :estado
        ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':estado', $estado);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    // Obtener reservas próximas (para notificaciones)
    public function getUpcomingReservations($horas = 24)
    {
        $sqlQuery = "SELECT
            r.id_reserva,
            r.codigo_reserva,
            r.fecha_reserva,
            r.hora_reserva,
            r.numero_personas,
            r.estado,
            m.numero_mesa,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN CONCAT(c.nombre, ' ', c.apellido)
                WHEN r.id_usuario IS NOT NULL THEN CONCAT(u.nombre, ' ', u.apellido)
                ELSE 'Sin asignar'
            END as nombre_completo,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN c.telefono
                WHEN r.id_usuario IS NOT NULL THEN u.telefono
                ELSE NULL
            END as telefono
        FROM reservas r
        LEFT JOIN mesas m ON r.id_mesa = m.id_mesa
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
        WHERE r.estado IN ('pendiente', 'confirmada')
        AND CONCAT(r.fecha_reserva, ' ', r.hora_reserva) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :horas HOUR)
        ORDER BY r.fecha_reserva ASC, r.hora_reserva ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':horas', $horas, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    // Estadísticas básicas de reservas
    public function getStats($fecha_desde = null, $fecha_hasta = null)
    {
        $sqlQuery = "SELECT
            COUNT(*) as total_reservas,
            COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
            COUNT(CASE WHEN estado = 'confirmada' THEN 1 END) as confirmadas,
            COUNT(CASE WHEN estado = 'cancelada' THEN 1 END) as canceladas,
            COUNT(CASE WHEN estado = 'no_show' THEN 1 END) as no_shows,
            COUNT(CASE WHEN tipo_reserva = 'online' THEN 1 END) as online,
            COUNT(CASE WHEN tipo_reserva = 'telefono' THEN 1 END) as telefono,
            COUNT(CASE WHEN tipo_reserva = 'presencial' THEN 1 END) as presencial,
            AVG(numero_personas) as promedio_personas
        FROM reservas r";

        $conditions = [];
        $params = [];

        if ($fecha_desde) {
            $conditions[] = "r.fecha_reserva >= :fecha_desde";
            $params[':fecha_desde'] = $fecha_desde;
        }

        if ($fecha_hasta) {
            $conditions[] = "r.fecha_reserva <= :fecha_hasta";
            $params[':fecha_hasta'] = $fecha_hasta;
        }

        if (!empty($conditions)) {
            $sqlQuery .= " WHERE " . implode(' AND ', $conditions);
        }

        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        $queryStatement->execute();
        return $queryStatement->fetch();
    }

    // Obtener reservas que requieren confirmación
    public function getPendingConfirmation($horas_limite = 2)
    {
        $sqlQuery = "SELECT
            r.id_reserva,
            r.codigo_reserva,
            r.fecha_reserva,
            r.hora_reserva,
            r.numero_personas,
            r.fecha_creacion,
            m.numero_mesa,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN CONCAT(c.nombre, ' ', c.apellido)
                WHEN r.id_usuario IS NOT NULL THEN CONCAT(u.nombre, ' ', u.apellido)
                ELSE 'Sin asignar'
            END as nombre_completo,
            CASE
                WHEN r.id_cliente IS NOT NULL THEN c.telefono
                WHEN r.id_usuario IS NOT NULL THEN u.telefono
                ELSE NULL
            END as telefono
        FROM reservas r
        LEFT JOIN mesas m ON r.id_mesa = m.id_mesa
        LEFT JOIN clientes c ON r.id_cliente = c.id_cliente
        LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
        WHERE r.estado = 'pendiente'
        AND CONCAT(r.fecha_reserva, ' ', r.hora_reserva) <= DATE_ADD(NOW(), INTERVAL :horas_limite HOUR)
        ORDER BY r.fecha_reserva ASC, r.hora_reserva ASC";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':horas_limite', $horas_limite, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }
}
