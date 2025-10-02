<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Horario
{
    private $dataBase;

    public function __construct()
    {
        $this->dataBase = Database::getConnection();
    }

    // Obtener todos los horarios
    public function getAll()
    {
        $sqlQuery = "SELECT id_horario, dia, hora_apertura, hora_cierre FROM horarios ORDER BY
        CASE dia
            WHEN 'lunes' THEN 1
            WHEN 'martes' THEN 2
            WHEN 'miercoles' THEN 3
            WHEN 'jueves' THEN 4
            WHEN 'viernes' THEN 5
            WHEN 'sabado' THEN 6
            WHEN 'domingo' THEN 7
        END";

        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->execute();

        return $queryStatement->fetchAll();
    }

    // Obtener horario por ID
    public function getById($id)
    {
        $sqlQuery = "SELECT id_horario, dia, hora_apertura, hora_cierre FROM horarios WHERE id_horario = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);
        $queryStatement->execute();

        return $queryStatement->fetch();
    }

    // Crear nuevo horario
    public function create($data)
    {
        $sqlQuery = "INSERT INTO horarios (dia, hora_apertura, hora_cierre) VALUES (:dia, :hora_apertura, :hora_cierre)";
        $queryStatement = $this->dataBase->prepare($sqlQuery);

        $queryStatement->bindParam(':dia', $data['dia']);
        $queryStatement->bindParam(':hora_apertura', $data['hora_apertura']);
        $queryStatement->bindParam(':hora_cierre', $data['hora_cierre']);

        if ($queryStatement->execute()) {
            return $this->getById($this->dataBase->lastInsertId());
        }

        return false;
    }

    // Actualizar horario
    public function update($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['dia'])) {
            $fields[] = "dia = :dia";
            $params[':dia'] = $data['dia'];
        }

        if (isset($data['hora_apertura'])) {
            $fields[] = "hora_apertura = :hora_apertura";
            $params[':hora_apertura'] = $data['hora_apertura'];
        }

        if (isset($data['hora_cierre'])) {
            $fields[] = "hora_cierre = :hora_cierre";
            $params[':hora_cierre'] = $data['hora_cierre'];
        }

        if (empty($fields)) {
            return $this->getById($id);
        }

        $sqlQuery = "UPDATE horarios SET " . implode(', ', $fields) . " WHERE id_horario = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);

        foreach ($params as $key => $value) {
            $queryStatement->bindValue($key, $value);
        }

        if ($queryStatement->execute()) {
            return $this->getById($id);
        }

        return false;
    }

    // Eliminar horario
    public function delete($id)
    {
        $sqlQuery = "DELETE FROM horarios WHERE id_horario = :id";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':id', $id, PDO::PARAM_INT);

        return $queryStatement->execute();
    }

    // Obtener horario por día
    public function getByDay($dia)
    {
        $sqlQuery = "SELECT id_horario, dia, hora_apertura, hora_cierre FROM horarios WHERE LOWER(dia) = LOWER(:dia)";
        $queryStatement = $this->dataBase->prepare($sqlQuery);
        $queryStatement->bindParam(':dia', $dia);
        $queryStatement->execute();

        return $queryStatement->fetch();
    }

    // Verificar si existe horario para un día (CORREGIDO)
    public function existsForDay($dia, $excludeId = null)
    {
        if ($excludeId) {
            $sqlQuery = "SELECT id_horario FROM horarios WHERE LOWER(dia) = LOWER(:dia) AND id_horario != :exclude_id";
            $queryStatement = $this->dataBase->prepare($sqlQuery);
            $queryStatement->bindParam(':dia', $dia);
            $queryStatement->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
        } else {
            $sqlQuery = "SELECT id_horario FROM horarios WHERE LOWER(dia) = LOWER(:dia)";
            $queryStatement = $this->dataBase->prepare($sqlQuery);
            $queryStatement->bindParam(':dia', $dia);
        }

        $queryStatement->execute();
        return $queryStatement->fetch() !== false;
    }

    // Verificar si una hora está dentro del horario de operación
    public function isWithinOperatingHours($dia, $hora)
    {
        $horario = $this->getByDay($dia);

        if (!$horario) {
            return false;
        }

        // Convertir a objetos DateTime para comparación adecuada
        $horaReserva = new \DateTime($hora);
        $horaApertura = new \DateTime($horario['hora_apertura']);
        $horaCierre = new \DateTime($horario['hora_cierre']);

        return $horaReserva >= $horaApertura && $horaReserva <= $horaCierre;
    }

    // Obtener horarios disponibles para un día específico
    public function getAvailableHours($dia, $intervalo = 30)
    {
        $horario = $this->getByDay($dia);

        if (!$horario) {
            return [];
        }

        $horas = [];
        $inicio = new \DateTime($horario['hora_apertura']);
        $fin = new \DateTime($horario['hora_cierre']);

        // Asegurar que no incluimos la hora exacta de cierre
        $fin->modify("-1 second");

        $interval = new \DateInterval('PT' . $intervalo . 'M');
        $periodo = new \DatePeriod($inicio, $interval, $fin);

        foreach ($periodo as $hora) {
            $horas[] = $hora->format('H:i:s');
        }

        return $horas;
    }
}
