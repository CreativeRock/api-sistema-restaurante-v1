<?php

namespace App\Controllers;

use App\Models\Horario;
use App\Utils\Response;
use App\Middlewares\RolMiddleware;

class HorarioController extends BaseController
{
    private $horarioModel;

    public function __construct()
    {
        $this->horarioModel = new Horario();
    }

    // GET /horarios - Obtener todos los horarios
    public function index()
    {
        RolMiddleware::staffOnly();

        try {
            $horarios = $this->horarioModel->getAll();
            Response::success($horarios, 'Horarios obtenidos correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener horarios: ' . $error->getMessage(), 500);
        }
    }

    // GET /horarios/{id} - Obtener horario por ID
    public function show($id)
    {
        RolMiddleware::staffOnly();

        if (!$this->isValidId($id)) {
            Response::error('ID de horario inválido');
            return;
        }

        try {
            $horario = $this->horarioModel->getById($id);

            if (!$horario) {
                Response::notFound('Horario no encontrado');
                return;
            }

            Response::success($horario, 'Horario obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener horario: ' . $error->getMessage(), 500);
        }
    }

    // POST /horarios - Crear nuevo horario
    public function store()
    {
        RolMiddleware::adminOnly();

        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        $this->validateRequerid($data, ['dia', 'hora_apertura', 'hora_cierre']);
        $data = $this->sanitizeInput($data);

        // Validar formato de horas
        if (!$this->validateTime($data['hora_apertura']) || !$this->validateTime($data['hora_cierre'])) {
            Response::validationError(['horario' => 'Formato de hora inválido (HH:MM:SS)']);
            return;
        }

        // Verificar que hora apertura < hora cierre
        if (strtotime($data['hora_apertura']) >= strtotime($data['hora_cierre'])) {
            Response::validationError(['horario' => 'La hora de apertura debe ser anterior a la hora de cierre']);
            return;
        }

        // Verificar que no existe ya un horario para este día
        if ($this->horarioModel->existsForDay($data['dia'])) {
            Response::validationError(['dia' => 'Ya existe un horario configurado para este día']);
            return;
        }

        try {
            $horario = $this->horarioModel->create($data);

            if (!$horario) {
                Response::error('Error al crear horario', 500);
                return;
            }

            Response::success($horario, 'Horario creado correctamente', 201);
        } catch (\Exception $error) {
            Response::error('Error al crear horario: ' . $error->getMessage(), 500);
        }
    }

    // PUT /horarios/{id} - Actualizar horario
    public function update($id)
    {
        RolMiddleware::adminOnly();

        if (!$this->isValidId($id)) {
            Response::error('ID de horario inválido');
            return;
        }

        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        try {
            $horario = $this->horarioModel->getById($id);
            if (!$horario) {
                Response::notFound('Horario no encontrado');
                return;
            }

            $data = $this->sanitizeInput($data);

            // Validaciones
            if (isset($data['hora_apertura']) && isset($data['hora_cierre'])) {
                if (strtotime($data['hora_apertura']) >= strtotime($data['hora_cierre'])) {
                    Response::validationError(['horario' => 'La hora de apertura debe ser anterior a la hora de cierre']);
                    return;
                }
            } elseif (isset($data['hora_apertura']) && !isset($data['hora_cierre'])) {
                if (strtotime($data['hora_apertura']) >= strtotime($horario['hora_cierre'])) {
                    Response::validationError(['horario' => 'La hora de apertura debe ser anterior a la hora de cierre actual']);
                    return;
                }
            } elseif (!isset($data['hora_apertura']) && isset($data['hora_cierre'])) {
                if (strtotime($horario['hora_apertura']) >= strtotime($data['hora_cierre'])) {
                    Response::validationError(['horario' => 'La hora de apertura actual debe ser anterior a la hora de cierre']);
                    return;
                }
            }

            if (isset($data['dia']) && $this->horarioModel->existsForDay($data['dia'], $id)) {
                Response::validationError(['dia' => 'Ya existe un horario configurado para este día']);
                return;
            }

            $horarioActualizado = $this->horarioModel->update($id, $data);

            if (!$horarioActualizado) {
                Response::error('Error al actualizar horario', 500);
                return;
            }

            Response::success($horarioActualizado, 'Horario actualizado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al actualizar horario: ' . $error->getMessage(), 500);
        }
    }

    // DELETE /horarios/{id} - Eliminar horario
    public function delete($id)
    {
        RolMiddleware::adminOnly();

        if (!$this->isValidId($id)) {
            Response::error('ID de horario inválido');
            return;
        }

        try {
            if (!$this->horarioModel->getById($id)) {
                Response::notFound('Horario no encontrado');
                return;
            }

            $result = $this->horarioModel->delete($id);

            if (!$result) {
                Response::error('Error al eliminar horario', 500);
                return;
            }

            Response::success(null, 'Horario eliminado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al eliminar horario: ' . $error->getMessage(), 500);
        }
    }

    // GET /horarios/dia/{dia} - Obtener horario por día
    public function getByDay($dia)
    {
        $dia = strtolower($dia);
        $diasValidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

        if (!in_array($dia, $diasValidos)) {
            Response::error('Día inválido. Use: lunes, martes, miercoles, jueves, viernes, sabado, domingo');
            return;
        }

        try {
            $horario = $this->horarioModel->getByDay($dia);

            if (!$horario) {
                Response::notFound('No hay horario configurado para ' . $dia);
                return;
            }

            Response::success($horario, 'Horario obtenido correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener horario: ' . $error->getMessage(), 500);
        }
    }
}