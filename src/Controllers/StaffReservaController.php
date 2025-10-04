<?php

namespace App\Controllers;

use App\Models\Reserva;
use App\Models\Mesa;
use App\Models\Horario;
use App\Models\HistorialReserva;
use App\Utils\Response;
use App\Middlewares\AuthMiddleware;

class StaffReservaController extends BaseController
{
    private $reservaModel;
    private $mesaModel;
    private $horarioModel;
    private $historialModel;

    public function __construct()
    {
        AuthMiddleware::checkRole(['Admin', 'Gerente', 'Mesero', 'Recepcionista']);

        $this->reservaModel = new Reserva();
        $this->mesaModel = new Mesa();
        $this->horarioModel = new Horario();
        $this->historialModel = new HistorialReserva();
    }

    // GET /staff/reservas - Obtener todas las reservas
    public function index()
    {
        try {
            $filters = $_GET;
            $reservas = $this->reservaModel->getAll($filters);
            Response::success($reservas, 'Reservas obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reservas: ' . $error->getMessage(), 500);
        }
    }

    // GET /staff/reservas/{id} - Obtener reserva por ID
    public function show($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de reserva inválido');
            return;
        }

        try {
            $reserva = $this->reservaModel->getById($id);

            if (!$reserva) {
                Response::notFound('Reserva no encontrada');
                return;
            }

            Response::success($reserva, 'Reserva obtenida correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reserva: ' . $error->getMessage(), 500);
        }
    }

    // POST /staff/reservas - Crear reserva (por parte del staff)
    public function store()
    {
        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        // Validar campos requeridos
        $this->validateRequerid($data, ['id_mesa', 'fecha_reserva', 'hora_reserva', 'numero_personas']);

        // Limpiar datos
        $data = $this->sanitizeInput($data);

        // Validaciones
        if (!$this->isValidId($data['id_mesa'])) {
            Response::validationError(['id_mesa' => 'ID de mesa inválido']);
            return;
        }

        if (!$this->validateDate($data['fecha_reserva'])) {
            Response::validationError(['fecha_reserva' => 'Formato de fecha inválido (YYYY-MM-DD)']);
            return;
        }

        if (!$this->validateTime($data['hora_reserva'])) {
            Response::validationError(['hora_reserva' => 'Formato de hora inválido (HH:MM:SS)']);
            return;
        }

        if (!is_numeric($data['numero_personas']) || $data['numero_personas'] < 1) {
            Response::validationError(['numero_personas' => 'Número de personas debe ser mayor a 0']);
            return;
        }

        try {
            // Verificar mesa
            if (!$this->mesaModel->getById($data['id_mesa'])) {
                Response::validationError(['id_mesa' => 'La mesa especificada no existe']);
                return;
            }

            // Verificar disponibilidad
            if (!$this->reservaModel->checkAvailability($data['id_mesa'], $data['fecha_reserva'], $data['hora_reserva'])) {
                Response::validationError(['disponibilidad' => 'La mesa no está disponible en la fecha y hora especificada']);
                return;
            }

            // Verificar horario
            $dayName = $this->getDayName($data['fecha_reserva']);
            if (!$this->horarioModel->isWithinOperatingHours($dayName, $data['hora_reserva'])) {
                Response::validationError(['hora_reserva' => 'La hora está fuera del horario de atención']);
                return;
            }

            // Asignar usuario (staff)
            $usuario = AuthMiddleware::getCurrentUser();
            $data['id_usuario'] = $usuario['id'];
            $data['id_cliente'] = $data['id_cliente'] ?? null; // Puede ser nulo si no se asigna a cliente
            $data['tipo_reserva'] = $data['tipo_reserva'] ?? 'telefono';

            // Valores por defecto
            $data['estado'] = $data['estado'] ?? 'pendiente';
            $data['notas'] = $data['notas'] ?? null;

            // Generar código único
            $data['codigo_reserva'] = $this->generateReservationCode();

            $reserva = $this->reservaModel->create($data);

            if (!$reserva) {
                Response::error('Error al crear reserva', 500);
                return;
            }

            // Registrar en historial
            $this->historialModel->create([
                'id_reserva' => $reserva['id_reserva'],
                'id_usuario' => $usuario['id'],
                'accion' => 'creacion',
                'detalle' => "Reserva creada por el staff - Código: {$data['codigo_reserva']}"
            ]);

            Response::success($reserva, 'Reserva creada correctamente', 201);
        } catch (\Exception $error) {
            Response::error('Error al crear reserva: ' . $error->getMessage(), 500);
        }
    }

    // PUT /staff/reservas/{id} - Actualizar reserva
    public function update($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de reserva inválido');
            return;
        }

        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        try {
            $reserva = $this->reservaModel->getById($id);

            if (!$reserva) {
                Response::notFound('Reserva no encontrada');
                return;
            }

            // Validar campos que se pueden actualizar
            $allowedFields = ['id_mesa', 'fecha_reserva', 'hora_reserva', 'numero_personas', 'estado', 'notas', 'tipo_reserva', 'id_cliente'];
            $updateData = array_intersect_key($data, array_flip($allowedFields));

            if (empty($updateData)) {
                Response::error('No se proporcionaron campos válidos para actualizar');
                return;
            }

            // Validaciones específicas
            if (isset($updateData['id_mesa']) && !$this->isValidId($updateData['id_mesa'])) {
                Response::validationError(['id_mesa' => 'ID de mesa inválido']);
                return;
            }

            if (isset($updateData['fecha_reserva']) && !$this->validateDate($updateData['fecha_reserva'])) {
                Response::validationError(['fecha_reserva' => 'Formato de fecha inválido']);
                return;
            }

            if (isset($updateData['hora_reserva']) && !$this->validateTime($updateData['hora_reserva'])) {
                Response::validationError(['hora_reserva' => 'Formato de hora inválido']);
                return;
            }

            if (isset($updateData['numero_personas']) && (!is_numeric($updateData['numero_personas']) || $updateData['numero_personas'] < 1)) {
                Response::validationError(['numero_personas' => 'Número de personas debe ser mayor a 0']);
                return;
            }

            // Verificar disponibilidad si se cambian datos críticos
            $needsAvailabilityCheck = isset($updateData['id_mesa']) || isset($updateData['fecha_reserva']) || isset($updateData['hora_reserva']);
            $id_mesa = $updateData['id_mesa'] ?? $reserva['id_mesa'];
            $fecha = $updateData['fecha_reserva'] ?? $reserva['fecha_reserva'];
            $hora = $updateData['hora_reserva'] ?? $reserva['hora_reserva'];

            if ($needsAvailabilityCheck) {
                if (!$this->reservaModel->checkAvailability($id_mesa, $fecha, $hora, $id)) {
                    Response::validationError(['disponibilidad' => 'La mesa no está disponible en la fecha y hora especificada']);
                    return;
                }

                // Verificar horarios
                $dayName = $this->getDayName($fecha);
                if (!$this->horarioModel->isWithinOperatingHours($dayName, $hora)) {
                    Response::validationError(['hora_reserva' => 'La hora está fuera del horario de atención']);
                    return;
                }
            }

            // Actualizar
            $reservaActualizada = $this->reservaModel->update($id, $updateData);

            if (!$reservaActualizada) {
                Response::error('Error al actualizar reserva', 500);
                return;
            }

            // Registrar en historial
            $usuario = AuthMiddleware::getCurrentUser();
            $cambios = $this->detectarCambios($reserva, $updateData);
            if (!empty($cambios)) {
                $this->historialModel->create([
                    'id_reserva' => $id,
                    'id_usuario' => $usuario['id'],
                    'accion' => 'modificacion',
                    'detalle' => "Reserva modificada - Cambios: " . implode(', ', $cambios)
                ]);
            }

            Response::success($reservaActualizada, 'Reserva actualizada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al actualizar reserva: ' . $error->getMessage(), 500);
        }
    }

    // PUT /staff/reservas/{id}/cancelar - Cancelar reserva (por staff)
    public function cancel($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de reserva inválido');
            return;
        }

        try {
            $reserva = $this->reservaModel->getById($id);

            if (!$reserva) {
                Response::notFound('Reserva no encontrada');
                return;
            }

            // Cambiar estado a cancelada
            $result = $this->reservaModel->changeStatus($id, 'cancelada');

            if (!$result) {
                Response::error('Error al cancelar reserva', 500);
                return;
            }

            // Registrar en historial
            $usuario = AuthMiddleware::getCurrentUser();
            $this->historialModel->create([
                'id_reserva' => $id,
                'id_usuario' => $usuario['id'],
                'accion' => 'cancelacion',
                'detalle' => "Reserva cancelada por el staff - Código: {$reserva['codigo_reserva']}"
            ]);

            Response::success($result, 'Reserva cancelada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al cancelar reserva: ' . $error->getMessage(), 500);
        }
    }

    // DELETE /staff/reservas/{id} - Eliminar reserva (solo admin)
    public function delete($id)
    {
        AuthMiddleware::checkRole(['Admin']); // Solo admin puede eliminar

        if (!$this->isValidId($id)) {
            Response::error('ID de reserva inválido');
            return;
        }

        try {
            $reserva = $this->reservaModel->getById($id);
            if (!$reserva) {
                Response::notFound('Reserva no encontrada');
                return;
            }

            // Registrar en historial antes de eliminar
            $usuario = AuthMiddleware::getCurrentUser();
            $this->historialModel->create([
                'id_reserva' => $id,
                'id_usuario' => $usuario['id'],
                'accion' => 'eliminacion',
                'detalle' => "Reserva eliminada permanentemente - Código: {$reserva['codigo_reserva']}"
            ]);

            $result = $this->reservaModel->delete($id);

            if (!$result) {
                Response::error('Error al eliminar reserva', 500);
                return;
            }

            Response::success(null, 'Reserva eliminada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al eliminar reserva: ' . $error->getMessage(), 500);
        }
    }

    // GET /staff/reservas/{id}/historial - Obtener historial de reserva
    public function getHistorial($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de reserva inválido');
            return;
        }

        try {
            $historial = $this->historialModel->getByReserva($id);
            Response::success($historial, 'Historial de reserva obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener historial: ' . $error->getMessage(), 500);
        }
    }

    // PUT /staff/reservas/{id}/estado - Cambiar estado de reserva
    public function changeStatus($id)
    {
        $data = $this->getJsonInput();
        if (!$data || !isset($data['estado'])) {
            Response::error('Estado requerido');
            return;
        }

        $validStates = ['pendiente', 'confirmada', 'cancelada', 'no_show'];
        if (!in_array($data['estado'], $validStates)) {
            Response::error('Estado inválido');
            return;
        }

        try {
            $reserva = $this->reservaModel->getById($id);
            if (!$reserva) {
                Response::notFound('Reserva no encontrada');
                return;
            }

            $result = $this->reservaModel->changeStatus($id, $data['estado']);

            if (!$result) {
                Response::error('Error al cambiar estado de reserva', 500);
                return;
            }

            // Registrar en historial
            $usuario = AuthMiddleware::getCurrentUser();
            $this->historialModel->create([
                'id_reserva' => $id,
                'id_usuario' => $usuario['id'],
                'accion' => 'modificacion',
                'detalle' => "Estado cambiado de '{$reserva['estado']}' a '{$data['estado']}'"
            ]);

            Response::success($result, 'Estado de reserva actualizado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al cambiar estado: ' . $error->getMessage(), 500);
        }
    }

    // GET /staff/reservas/cliente/{id_cliente} - Obtener reservas por cliente
    public function getByCliente($id_cliente)
    {
        if (!$this->isValidId($id_cliente)) {
            Response::error('ID de cliente inválido');
            return;
        }

        try {
            $reservas = $this->reservaModel->getByCliente($id_cliente);
            Response::success($reservas, 'Reservas del cliente obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reservas: ' . $error->getMessage(), 500);
        }
    }

    // GET /staff/reservas/fecha/{fecha} - Obtener reservas por fecha
    public function getByDate($fecha)
    {
        if (!$this->validateDate($fecha)) {
            Response::error('Formato de fecha inválido');
            return;
        }

        try {
            $reservas = $this->reservaModel->getByDate($fecha);
            Response::success($reservas, 'Reservas de la fecha obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reservas: ' . $error->getMessage(), 500);
        }
    }

    // GET /staff/reservas/estado/{estado} - Obtener reservas por estado
    public function getByStatus($estado)
    {
        $validStates = ['pendiente', 'confirmada', 'cancelada', 'no_show'];
        if (!in_array($estado, $validStates)) {
            Response::error('Estado de reserva inválido');
            return;
        }

        try {
            $reservas = $this->reservaModel->getByStatus($estado);
            Response::success($reservas, 'Reservas por estado obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reservas: ' . $error->getMessage(), 500);
        }
    }

    // GET /staff/reservas/disponibilidad - Verificar disponibilidad
    public function checkAvailability()
    {
        $mesa = $_GET['mesa'] ?? null;
        $fecha = $_GET['fecha'] ?? null;
        $hora = $_GET['hora'] ?? null;

        if (!$mesa || !$fecha || !$hora) {
            Response::error('Parámetros mesa, fecha y hora son requeridos');
            return;
        }

        if (!$this->isValidId($mesa) || !$this->validateDate($fecha) || !$this->validateTime($hora)) {
            Response::error('Parámetros inválidos');
            return;
        }

        try {
            $available = $this->reservaModel->checkAvailability($mesa, $fecha, $hora);
            Response::success(['disponible' => $available], 'Disponibilidad verificada');
        } catch (\Exception $error) {
            Response::error('Error al verificar disponibilidad: ' . $error->getMessage(), 500);
        }
    }

    // GET /staff/reservas/estadisticas - Obtener estadísticas
    public function getStats()
    {
        $fecha_desde = $_GET['fecha_desde'] ?? null;
        $fecha_hasta = $_GET['fecha_hasta'] ?? null;

        try {
            $stats = $this->reservaModel->getStats($fecha_desde, $fecha_hasta);
            Response::success($stats, 'Estadísticas obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener estadísticas: ' . $error->getMessage(), 500);
        }
    }

    // GET /staff/reservas/proximas - Obtener reservas próximas
    public function getUpcoming()
    {
        $horas = $_GET['horas'] ?? 24;

        try {
            $reservas = $this->reservaModel->getUpcomingReservations($horas);
            Response::success($reservas, 'Reservas próximas obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reservas próximas: ' . $error->getMessage(), 500);
        }
    }

    // GET /staff/reservas/pendientes-confirmacion - Reservas pendientes de confirmación
    public function getPendingConfirmation()
    {
        $horas_limite = $_GET['horas_limite'] ?? 2;

        try {
            $reservas = $this->reservaModel->getPendingConfirmation($horas_limite);
            Response::success($reservas, 'Reservas pendientes de confirmación obtenidas');
        } catch (\Exception $error) {
            Response::error('Error al obtener reservas pendientes: ' . $error->getMessage(), 500);
        }
    }

    // Métodos auxiliares
    private function generateReservationCode()
    {
        return strtoupper(bin2hex(random_bytes(4))); // Ejemplo: A1B2C3D4
    }

    private function getDayName($date)
    {
        $days = [
            'Sunday' => 'domingo',
            'Monday' => 'lunes',
            'Tuesday' => 'martes',
            'Wednesday' => 'miercoles',
            'Thursday' => 'jueves',
            'Friday' => 'viernes',
            'Saturday' => 'sabado'
        ];

        $dayInEnglish = date('l', strtotime($date));
        return $days[$dayInEnglish] ?? $dayInEnglish;
    }

    private function detectarCambios($original, $nuevo)
    {
        $cambios = [];

        foreach ($nuevo as $campo => $valor) {
            if (isset($original[$campo]) && $original[$campo] != $valor) {
                $cambios[] = "$campo: {$original[$campo]} → $valor";
            }
        }

        return $cambios;
    }
}