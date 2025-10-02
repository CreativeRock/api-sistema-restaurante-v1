<?php

namespace App\Controllers;

use App\Models\HistorialReserva;
use App\Utils\Response;
use App\Middlewares\RolMiddleware;

class HistorialController extends BaseController
{
    private $historialModel;

    public function __construct()
    {
        $this->historialModel = new HistorialReserva();
    }

    // GET /historial - Obtener historial completo
    public function index()
    {
        RolMiddleware::staffOnly();

        try {
            $filters = $_GET;
            $historial = $this->historialModel->getAll($filters);
            Response::success($historial, 'Historial obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener historial: ' . $error->getMessage(), 500);
        }
    }

    // GET /historial/metricas - Obtener métricas de acciones
    public function getMetrics()
    {
        RolMiddleware::staffOnly();

        $fecha_desde = $_GET['fecha_desde'] ?? null;
        $fecha_hasta = $_GET['fecha_hasta'] ?? null;

        try {
            $metricas = $this->historialModel->getActionMetrics($fecha_desde, $fecha_hasta);
            Response::success($metricas, 'Métricas obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener métricas: ' . $error->getMessage(), 500);
        }
    }

    // GET /historial/resumen - Obtener resumen de acciones
    public function getSummary()
    {
        RolMiddleware::staffOnly();

        $fecha_desde = $_GET['fecha_desde'] ?? null;
        $fecha_hasta = $_GET['fecha_hasta'] ?? null;

        try {
            $resumen = $this->historialModel->getActionSummary($fecha_desde, $fecha_hasta);
            Response::success($resumen, 'Resumen obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener resumen: ' . $error->getMessage(), 500);
        }
    }

    // GET /historial/usuarios - Obtener actividad por usuario
    public function getUserActivity()
    {
        RolMiddleware::adminAndGerente();

        $fecha_desde = $_GET['fecha_desde'] ?? null;
        $fecha_hasta = $_GET['fecha_hasta'] ?? null;

        try {
            $actividad = $this->historialModel->getUserActivity($fecha_desde, $fecha_hasta);
            Response::success($actividad, 'Actividad de usuarios obtenida correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener actividad: ' . $error->getMessage(), 500);
        }
    }

    // GET /historial/reservas-modificadas - Obtener reservas más modificadas
    public function getMostModified()
    {
        RolMiddleware::staffOnly();

        $limit = $_GET['limit'] ?? 10;

        try {
            $reservas = $this->historialModel->getMostModifiedReservations($limit);
            Response::success($reservas, 'Reservas más modificadas obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener datos: ' . $error->getMessage(), 500);
        }
    }
}