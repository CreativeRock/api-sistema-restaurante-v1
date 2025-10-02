<?php

namespace App\Controllers;

use App\Models\Reserva;
use App\Models\Mesa;
use App\Models\Cliente;
use App\Models\Horario;
use App\Models\HistorialReserva;
use App\Utils\Response;
use App\Middlewares\RolMiddleware;
use App\Middlewares\ClienteAuthMiddleware;

class ReservaController extends BaseController
{
    private $reservaModel;
    private $mesaModel;
    private $clienteModel;
    private $horarioModel;
    private $historialModel;

    public function __construct()
    {
        $this->reservaModel = new Reserva();
        $this->mesaModel = new Mesa();
        $this->clienteModel = new Cliente();
        $this->horarioModel = new Horario();
        $this->historialModel = new HistorialReserva();
    }

    // GET /reservas - Obtener todas las reservas
    public function index()
    {
        // Solo personal autorizado puede ver todas las reservas
        RolMiddleware::staffOnly();

        try {
            $filters = $_GET;
            $reservas = $this->reservaModel->getAll($filters);
            Response::success($reservas, 'Reservas obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reservas: ' . $error->getMessage(), 500);
        }
    }

    // GET /reservas/{id} - Obtener reserva por ID
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

            // Verificar permisos: cliente solo puede ver sus reservas
            $currentUser = RolMiddleware::getCurrentUser();
            $currentCliente = ClienteAuthMiddleware::getCurrentCliente();

            if (!$currentUser && (!$currentCliente || $currentCliente['id'] != $reserva['id_cliente'])) {
                Response::error('No tiene permisos para ver esta reserva', 403);
                return;
            }

            Response::success($reserva, 'Reserva obtenida correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reserva: ' . $error->getMessage(), 500);
        }
    }

    // POST /reservas - Crear nueva reserva
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

        // Validaciones específicas
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
            // Verificar que la mesa existe
            if (!$this->mesaModel->getById($data['id_mesa'])) {
                Response::validationError(['id_mesa' => 'La mesa especificada no existe']);
                return;
            }

            // Verificar disponibilidad
            if (!$this->reservaModel->checkAvailability($data['id_mesa'], $data['fecha_reserva'], $data['hora_reserva'])) {
                Response::validationError(['disponibilidad' => 'La mesa no está disponible en la fecha y hora especificada']);
                return;
            }

            // Verificar horarios del restaurante
            $dayName = $this->getDayName($data['fecha_reserva']);
            if (!$this->horarioModel->isWithinOperatingHours($dayName, $data['hora_reserva'])) {
                Response::validationError(['hora_reserva' => 'La hora está fuera del horario de atención']);
                return;
            }

            // Determinar cliente y usuario
            $currentUser = RolMiddleware::getCurrentUser();
            $currentCliente = ClienteAuthMiddleware::getCurrentCliente();

            if ($currentCliente) {
                $data['id_cliente'] = $currentCliente['id'];
                $data['tipo_reserva'] = 'online';
                $data['id_usuario'] = null;
                $usuarioHistorial = null; // Los clientes no generan historial directo
            } elseif ($currentUser) {
                $data['id_usuario'] = $currentUser['id'];
                $data['id_cliente'] = null;
                $usuarioHistorial = $currentUser['id'];
                if (!isset($data['tipo_reserva'])) {
                    $data['tipo_reserva'] = 'telefono';
                }
            } else {
                Response::error('Usuario no autenticado', 401);
                return;
            }

            // Generar código único de reserva
            $data['codigo_reserva'] = $this->generateReservationCode();

            // Valores por defecto
            if (!isset($data['estado'])) {
                $data['estado'] = 'pendiente';
            }

            if (!isset($data['notas'])) {
                $data['notas'] = null;
            }

            $reserva = $this->reservaModel->create($data);

            if (!$reserva) {
                Response::error('Error al crear reserva', 500);
                return;
            }

            // Registrar en historial solo si fue creada por un usuario (no cliente)
            if ($usuarioHistorial) {
                $this->historialModel->create([
                    'id_reserva' => $reserva['id_reserva'],
                    'id_usuario' => $usuarioHistorial,
                    'accion' => 'creacion',
                    'detalle' => "Reserva creada - Mesa: {$reserva['numero_mesa']}, Fecha: {$data['fecha_reserva']}, Hora: {$data['hora_reserva']}, Personas: {$data['numero_personas']}"
                ]);
            }

            Response::success($reserva, 'Reserva creada correctamente', 201);
        } catch (\Exception $error) {
            Response::error('Error al crear reserva: ' . $error->getMessage(), 500);
        }
    }

    // PUT /reservas/{id} - Actualizar reserva
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
            // Verificar que la reserva existe
            $reservaOriginal = $this->reservaModel->getById($id);
            if (!$reservaOriginal) {
                Response::notFound('Reserva no encontrada');
                return;
            }

            // Verificar permisos
            $currentUser = RolMiddleware::getCurrentUser();
            $currentCliente = ClienteAuthMiddleware::getCurrentCliente();

            if (!$currentUser && (!$currentCliente || $currentCliente['id'] != $reservaOriginal['id_cliente'])) {
                Response::error('No tiene permisos para modificar esta reserva', 403);
                return;
            }

            // Validaciones si se cambian fecha/hora/mesa
            if (isset($data['fecha_reserva']) && !$this->validateDate($data['fecha_reserva'])) {
                Response::validationError(['fecha_reserva' => 'Formato de fecha inválido']);
                return;
            }

            if (isset($data['hora_reserva']) && !$this->validateTime($data['hora_reserva'])) {
                Response::validationError(['hora_reserva' => 'Formato de hora inválido']);
                return;
            }

            if (isset($data['id_mesa']) && !$this->isValidId($data['id_mesa'])) {
                Response::validationError(['id_mesa' => 'ID de mesa inválido']);
                return;
            }

            if (isset($data['numero_personas']) && (!is_numeric($data['numero_personas']) || $data['numero_personas'] < 1)) {
                Response::validationError(['numero_personas' => 'Número de personas debe ser mayor a 0']);
                return;
            }

            // Verificar disponibilidad si se cambian datos críticos
            $needsAvailabilityCheck = isset($data['id_mesa']) || isset($data['fecha_reserva']) || isset($data['hora_reserva']);

            if ($needsAvailabilityCheck) {
                $checkMesa = $data['id_mesa'] ?? $reservaOriginal['id_mesa'];
                $checkFecha = $data['fecha_reserva'] ?? $reservaOriginal['fecha_reserva'];
                $checkHora = $data['hora_reserva'] ?? $reservaOriginal['hora_reserva'];

                if (!$this->reservaModel->checkAvailability($checkMesa, $checkFecha, $checkHora, $id)) {
                    Response::validationError(['disponibilidad' => 'La mesa no está disponible en la fecha y hora especificada']);
                    return;
                }

                // Verificar horarios si se cambia fecha u hora
                if (isset($data['fecha_reserva']) || isset($data['hora_reserva'])) {
                    $dayName = $this->getDayName($checkFecha);
                    if (!$this->horarioModel->isWithinOperatingHours($dayName, $checkHora)) {
                        Response::validationError(['hora_reserva' => 'La hora está fuera del horario de atención']);
                        return;
                    }
                }
            }

            // Limpiar datos
            $data = $this->sanitizeInput($data);

            $reservaActualizada = $this->reservaModel->update($id, $data);

            if (!$reservaActualizada) {
                Response::error('Error al actualizar reserva', 500);
                return;
            }

            // Registrar en historial solo si fue modificada por un usuario
            if ($currentUser) {
                $cambios = $this->detectarCambios($reservaOriginal, $data);
                if (!empty($cambios)) {
                    $this->historialModel->create([
                        'id_reserva' => $id,
                        'id_usuario' => $currentUser['id'],
                        'accion' => 'modificacion',
                        'detalle' => "Reserva modificada - Cambios: " . implode(', ', $cambios)
                    ]);
                }
            }

            Response::success($reservaActualizada, 'Reserva actualizada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al actualizar reserva: ' . $error->getMessage(), 500);
        }
    }

    // PUT /reservas/{id}/cancelar - Cancelar reserva (soft delete)
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

            // Verificar permisos
            $currentUser = RolMiddleware::getCurrentUser();
            $currentCliente = ClienteAuthMiddleware::getCurrentCliente();

            if (!$currentUser && (!$currentCliente || $currentCliente['id'] != $reserva['id_cliente'])) {
                Response::error('No tiene permisos para cancelar esta reserva', 403);
                return;
            }

            // Cambiar estado a cancelada
            $result = $this->reservaModel->changeStatus($id, 'cancelada');

            if (!$result) {
                Response::error('Error al cancelar reserva', 500);
                return;
            }

            // Registrar en historial
            $usuarioHistorial = $currentUser ? $currentUser['id'] : null;
            $tipoUsuario = $currentUser ? 'usuario' : 'cliente';

            if ($usuarioHistorial) {
                $this->historialModel->create([
                    'id_reserva' => $id,
                    'id_usuario' => $usuarioHistorial,
                    'accion' => 'cancelacion',
                    'detalle' => "Reserva cancelada por {$tipoUsuario} - Código: {$reserva['codigo_reserva']}"
                ]);
            }

            Response::success($result, 'Reserva cancelada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al cancelar reserva: ' . $error->getMessage(), 500);
        }
    }

    // DELETE /reservas/{id} - Eliminar reserva (solo para administradores)
    public function delete($id)
    {
        // Solo administradores pueden eliminar reservas permanentemente
        RolMiddleware::adminOnly();

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

            $currentUser = RolMiddleware::getCurrentUser();

            // Registrar en historial antes de eliminar
            $this->historialModel->create([
                'id_reserva' => $id,
                'id_usuario' => $currentUser['id'],
                'accion' => 'cancelacion',
                'detalle' => "Reserva eliminada permanentemente - Código: {$reserva['codigo_reserva']} - Motivo: Eliminación administrativa"
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

    // GET /reservas/{id}/historial - Obtener historial de una reserva
    public function getHistorial($id)
    {
        RolMiddleware::staffOnly();

        if (!$this->isValidId($id)) {
            Response::error('ID de reserva inválido');
            return;
        }

        try {
            if (!$this->reservaModel->getById($id)) {
                Response::notFound('Reserva no encontrada');
                return;
            }

            $historial = $this->historialModel->getByReserva($id);
            Response::success($historial, 'Historial de reserva obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener historial: ' . $error->getMessage(), 500);
        }
    }

    public function getByCliente($id_cliente)
    {
        if (!$this->isValidId($id_cliente)) {
            Response::error('ID de cliente inválido');
            return;
        }

        // Verificar permisos
        $currentUser = RolMiddleware::getCurrentUser();
        $currentCliente = ClienteAuthMiddleware::getCurrentCliente();

        if (!$currentUser && (!$currentCliente || $currentCliente['id'] != $id_cliente)) {
            Response::error('No tiene permisos para ver estas reservas', 403);
            return;
        }

        try {
            $reservas = $this->reservaModel->getByCliente($id_cliente);
            Response::success($reservas, 'Reservas del cliente obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reservas: ' . $error->getMessage(), 500);
        }
    }

    public function getByDate($fecha)
    {
        RolMiddleware::staffOnly();

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

    public function getByStatus($estado)
    {
        RolMiddleware::staffOnly();

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

    public function changeStatus($id)
    {
        RolMiddleware::staffOnly();

        if (!$this->isValidId($id)) {
            Response::error('ID de reserva inválido');
            return;
        }

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
            $currentUser = RolMiddleware::getCurrentUser();
            $this->historialModel->create([
                'id_reserva' => $id,
                'id_usuario' => $currentUser['id'],
                'accion' => 'modificacion',
                'detalle' => "Estado cambiado de '{$reserva['estado']}' a '{$data['estado']}'"
            ]);

            Response::success($result, 'Estado de reserva actualizado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al cambiar estado: ' . $error->getMessage(), 500);
        }
    }

    //Verificar disponibilidad /reservas/
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
        return $days[$dayInEnglish];
    }

    private function generateReservationCode()
    {
        do {
            $code = strtoupper(substr(uniqid(), -6));
        } while ($this->reservaModel->codeExists($code));

        return $code;
    }

    private function detectarCambios($original, $nuevos)
    {
        $cambios = [];

        if (isset($nuevos['id_mesa']) && $nuevos['id_mesa'] != $original['id_mesa']) {
            $cambios[] = "Mesa: {$original['numero_mesa']} → Mesa {$nuevos['id_mesa']}";
        }

        if (isset($nuevos['fecha_reserva']) && $nuevos['fecha_reserva'] != $original['fecha_reserva']) {
            $cambios[] = "Fecha: {$original['fecha_reserva']} → {$nuevos['fecha_reserva']}";
        }

        if (isset($nuevos['hora_reserva']) && $nuevos['hora_reserva'] != $original['hora_reserva']) {
            $cambios[] = "Hora: {$original['hora_reserva']} → {$nuevos['hora_reserva']}";
        }

        if (isset($nuevos['numero_personas']) && $nuevos['numero_personas'] != $original['numero_personas']) {
            $cambios[] = "Personas: {$original['numero_personas']} → {$nuevos['numero_personas']}";
        }

        if (isset($nuevos['estado']) && $nuevos['estado'] != $original['estado']) {
            $cambios[] = "Estado: {$original['estado']} → {$nuevos['estado']}";
        }

        return $cambios;
    }
}
