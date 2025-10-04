<?php

namespace App\Controllers;

use App\Models\Reserva;
use App\Models\Mesa;
use App\Models\Horario;
use App\Models\HistorialReserva;
use App\Utils\Response;
use App\Middlewares\ClienteAuthMiddleware;
use App\Controllers\BaseController;
use App\Middlewares\AuthMiddleware;

class ClienteReservaController extends BaseController
{
    private $reservaModel;
    private $mesaModel;
    private $horarioModel;
    private $historialModel;

    public function __construct()
    {
        AuthMiddleware::checkRole(['Cliente']);

        $this->reservaModel = new Reserva();
        $this->mesaModel = new Mesa();
        $this->horarioModel = new Horario();
        $this->historialModel = new HistorialReserva();
    }

    // GET /cliente/reservas - Obtener reservas del cliente autenticado
    public function index()
    {
        error_log("ClienteReservaController: Obteniendo reservas del cliente");

        // Obtener el cliente autenticado desde el middleware
        $cliente = AuthMiddleware::getCurrentCliente();

        if (!$cliente) {
            error_log("ClienteReservaController: No se pudo obtener cliente autenticado");
            Response::error('Cliente no autenticado', 401);
            return;
        }

        error_log("ClienteReservaController: Cliente ID: " . $cliente['id']);

        try {
            $reservas = $this->reservaModel->getByCliente($cliente['id']);

            error_log("ClienteReservaController: " . count($reservas) . " reservas encontradas");

            Response::success($reservas, 'Reservas obtenidas correctamente');
        } catch (\Exception $error) {
            error_log("ClienteReservaController: Error - " . $error->getMessage());
            Response::error('Error al obtener reservas: ' . $error->getMessage(), 500);
        }
    }

    //GET /cliente/reservas/{id} - Obtener reserva específica del cliente
    public function show($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de reserva iniválido');
            return;
        }

        try {
            $reserva = $this->reservaModel->getById($id);
            $cliente = AuthMiddleware::getCurrentCliente();

            if (!$reserva || $reserva['id_cliente'] != $cliente['id']) {
                Response::notFound('Reserva no econtrada');
                return;
            }
            Response::success($reserva, 'Reserva obtenida correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener reserva: ', $error->getMessage(), 500);
        }
    }

    //POST /cliente/reservas - Crear nueva reserva
    public function store()
    {
        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        // Validar campos requeridos
        $this->validateRequerid($data, ['id_mesa', 'fecha_reserva', 'hora_reserva', 'numero_personas']); // ← Agregué numero_personas

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

            // Asignar Cliente (desde sesión, NO desde JSON)
            $cliente = AuthMiddleware::getCurrentCliente();
            $data['id_cliente'] = $cliente['id']; // ← Sobrescribe el id_cliente del JSON
            $data['tipo_reserva'] = 'online';
            $data['id_usuario'] = null;

            // Valores por defecto
            $data['estado'] = $data['estado'] ?? 'pendiente';
            $data['notas'] = $data['notas'] ?? null;

            // Generar código de reserva
            $data['codigo_reserva'] = $this->generateReservationCode();

            // Crear reserva
            $reserva = $this->reservaModel->create($data);

            if (!$reserva) {
                Response::error('Error al crear reserva', 500);
                return;
            }

            Response::success($reserva, 'Reserva creada correctamente', 201);
        } catch (\Exception $error) {
            Response::error('Error al crear reserva: ' . $error->getMessage(), 500);
        }
    }

    //PUT /cliente/reservas/{id} - Actualizar reserva
    public function update($id)
    {
        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        // Limpiar datos
        $data = $this->sanitizeInput($data);

        try {
            // Obtener reserva actual
            $reservaActual = $this->reservaModel->getById($id);
            if (!$reservaActual) {
                Response::error('Reserva no encontrada');
                return;
            }

            // Verificar que el cliente es el dueño de la reserva
            $cliente = AuthMiddleware::getCurrentCliente();
            if ($reservaActual['id_cliente'] != $cliente['id']) {
                Response::error('No tienes permisos para modificar esta reserva', 403);
                return;
            }

            // Validar campos que se envían para actualizar
            if (isset($data['numero_personas'])) {
                if (!is_numeric($data['numero_personas']) || $data['numero_personas'] < 1) {
                    Response::validationError(['numero_personas' => 'Número de personas debe ser mayor a 0']);
                    return;
                }
            }

            // Solo validar fecha/hora si se están actualizando
            if (isset($data['fecha_reserva'])) {
                if (!$this->validateDate($data['fecha_reserva'])) {
                    Response::validationError(['fecha_reserva' => 'Formato de fecha inválido (YYYY-MM-DD)']);
                    return;
                }
            }

            if (isset($data['hora_reserva'])) {
                if (!$this->validateTime($data['hora_reserva'])) {
                    Response::validationError(['hora_reserva' => 'Formato de hora inválido (HH:MM:SS)']);
                    return;
                }
            }

            // Usar valores actuales para validaciones si no se envían nuevos valores
            $fechaValidar = isset($data['fecha_reserva']) ? $data['fecha_reserva'] : $reservaActual['fecha_reserva'];
            $horaValidar = isset($data['hora_reserva']) ? $data['hora_reserva'] : $reservaActual['hora_reserva'];
            $mesaValidar = isset($data['id_mesa']) ? $data['id_mesa'] : $reservaActual['id_mesa'];

            // Validar disponibilidad solo si se cambia fecha, hora o mesa
            if (isset($data['fecha_reserva']) || isset($data['hora_reserva']) || isset($data['id_mesa'])) {
                if (!$this->reservaModel->checkAvailability($mesaValidar, $fechaValidar, $horaValidar, $id)) {
                    Response::validationError(['disponibilidad' => 'La mesa no está disponible en la fecha y hora especificada']);
                    return;
                }
            }

            // Validar horario solo si se cambia fecha o hora
            if (isset($data['fecha_reserva']) || isset($data['hora_reserva'])) {
                $dayName = $this->getDayName($fechaValidar);
                if (!$this->horarioModel->isWithinOperatingHours($dayName, $horaValidar)) {
                    Response::validationError(['hora_reserva' => 'La hora está fuera del horario de atención']);
                    return;
                }
            }

            // Validar mesa si se cambia
            if (isset($data['id_mesa'])) {
                if (!$this->isValidId($data['id_mesa'])) {
                    Response::validationError(['id_mesa' => 'ID de mesa inválido']);
                    return;
                }

                if (!$this->mesaModel->getById($data['id_mesa'])) {
                    Response::validationError(['id_mesa' => 'La mesa especificada no existe']);
                    return;
                }
            }

            // Actualizar reserva
            $reservaActualizada = $this->reservaModel->update($id, $data);

            if (!$reservaActualizada) {
                Response::error('Error al actualizar reserva');
                return;
            }

            Response::success($reservaActualizada, 'Reserva actualizada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al actualizar reserva: ', $error->getMessage(), 500);
        }
    }

    // PUT /cliente/reservas/{id}/cancelar - Cancelar reserva
    public function cancel($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de reserva inválido');
            return;
        }

        try {
            $reserva = $this->reservaModel->getById($id);
            $cliente = AuthMiddleware::getCurrentCliente();

            if (!$reserva || $reserva['id_cliente'] != $cliente['id']) {
                Response::notFound('Reserva no encontrada o no autorizada');
                return;
            }

            // Cambiar estado a cancelada
            $result = $this->reservaModel->changeStatus($id, 'cancelada');

            if (!$result) {
                Response::error('Error al cancelar reserva', 500);
                return;
            }

            // Registrar en historial (opcional, si se desea registrar la cancelación del cliente)
            // $this->historialModel->create([
            //     'id_reserva' => $id,
            //     'id_usuario' => 999, // No hay usuario, es el cliente
            //     'accion' => 'cancelacion',
            //     'detalle' => "Reserva cancelada por el cliente - Código: {$reserva['codigo_reserva']}"
            // ]);

            Response::success($result, 'Reserva cancelada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al cancelar reserva: ' . $error->getMessage(), 500);
        }
    }

    // GET /cliente/reservas/disponibilidad - Verificar disponibilidad
    public function checkAvailability()
    {
        $mesa = $_GET['mesa'] ?? null;
        $fecha = $_GET['fecha'] ?? null;
        $hora = $_GET['hora'] ?? null;

        if (!$mesa || !$fecha || !$hora) {
            Response::error('Parámetros requeridos: mesa, fecha y hora');
            return;
        }

        // Validar fecha en múltiples formatos
        $isValidDate = $this->validateDate($fecha, 'Y-m-d') ||
            $this->validateDate($fecha, 'Y/m/d') ||
            $this->validateDate($fecha, 'd-m-Y') ||
            $this->validateDate($fecha, 'd/m/Y');

        // Validaciones individuales con mensajes específicos
        if (!$this->isValidId($mesa)) {
            Response::error('ID de mesa inválido. Debe ser un número positivo');
            return;
        }

        if (!$isValidDate) {
            Response::error('Formato de fecha inválido. Use: YYYY-MM-DD, YYYY/MM/DD, DD-MM-YYYY o DD/MM/YYYY');
            return;
        }

        if (!$this->validateTime($hora)) {
            Response::error('Formato de hora inválido. Use: HH:MM:SS (ej: 13:00:00)');
            return;
        }

        try {
            // Normalizar la fecha a formato YYYY-MM-DD para la base de datos
            $fechaNormalizada = $fecha;

            if (strpos($fecha, '/') !== false) {
                $fechaNormalizada = str_replace('/', '-', $fecha);
            }

            // Si la fecha está en formato DD-MM-YYYY, convertir a YYYY-MM-DD
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $fechaNormalizada, $matches)) {
                $fechaNormalizada = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }

            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fechaNormalizada, $matches)) {
                $fechaNormalizada = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }

            $available = $this->reservaModel->checkAvailability($mesa, $fechaNormalizada, $hora);

            Response::success([
                'disponible' => $available,
                'mesa_id' => (int)$mesa,
                'fecha' => $fechaNormalizada,
                'hora' => $hora
            ], 'Disponibilidad verificada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al verificar disponibilidad: ' . $error->getMessage(), 500);
        }
    }


    //Métodos Auxiliares
    private function generateReservationCode()
    {
        return strtoupper(bin2hex(random_bytes(4)));
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
}
