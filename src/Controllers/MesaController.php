<?php

namespace App\Utils\Debug;

namespace App\Controllers;

use App\Models\Mesa;
use App\Utils\Debug;
use App\Utils\Response;

class MesaController extends BaseController
{
    private $mesaModel;

    public function __construct()
    {
        $this->mesaModel = new Mesa();
    }

    //GET /mesas - obtener todas las mesas
    public function index()
    {
        // Verificar si es una consulta de disponibilidad
        if (isset($_GET['action']) && $_GET['action'] === 'disponibilidad') {
            return $this->checkDisponibilidad();
        }

        try {
            $mesas = $this->mesaModel->getAll();
            Response::success($mesas, 'Mesas obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener mesas: ' . $error->getMessage(), 500);
        }
    }

    //GET /mesas/id - obtener mesa por ID
    public function show($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de mesa inválido');
            return;
        }

        try {
            $mesa = $this->mesaModel->getById($id);

            if (!$mesa) {
                Response::notFound('Mesa no econtrada');
                return;
            }

            Response::success($mesa, 'Mesa obtenida correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener mesa: ' . $error->getMessage(), 500);
        }
    }

    //POST /mesas - Crear nueva mesa
    public function store()
    {
        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        //Validar campos requeridos
        $this->validateRequerid($data, ['numero_mesa', 'nombre_mesa', 'capacidad', 'ubicacion']);

        $data = $this->sanitizeInput($data);

        if (!is_numeric($data['capacidad']) || $data['capacidad'] < 1) {
            Response::validationError(['Capacidad' => 'La capacidad debe ser un número mayor a 0']);

            return;
        }

        if (strlen($data['nombre_mesa']) > 30) {
            Response::validationError(['nombre_mesa' => 'El nombre de mesa no puede exceder 30 caracteres']);
            return;
        }

        if (strlen($data['ubicacion']) > 60) {
            Response::validationError(['ubicacion' => 'La ubicación no puede exceder 60 caracteres']);
            return;
        }

        //Verificar si el numero de mesa ya existe
        if ($this->mesaModel->numeroMesaExists($data['numero_mesa'])) {
            Response::validationError(['numero_mesa' => 'El número de mesa ya está registrado']);
            return;
        }

        //Agregar valores por defecto para ENUMs si no se envián
        if (!isset($data['estado'])) {
            $data['estado'] = 'disponible';
        }

        if (!isset($data['tipo'])) {
            $data['tipo'] = 'Standard';
        }

        //Validar ENUMs
        if (!Mesa::isValidEstado($data['estado'])) {
            Response::validationError([
                'estado' => 'Estado inválido. Valores permitidos: ' . implode(', ', Mesa::getEstadosValidos())
            ]);
            return;
        }

        if (!Mesa::isValidTipo($data['tipo'])) {
            Response::validationError([
                'tipo' => 'Tipo inválido. Valores permitidos: ' . implode(', ', Mesa::getTiposValidos())
            ]);
            return;
        }

        //Agregar características vacías si no se envían
        if (!isset($data['caracteristicas'])) {
            $data['caracteristicas'] = '';
        }

        if (strlen($data['caracteristicas']) > 255) {
            Response::validationError(['caracteristicas' => 'Las características no pueden exceder 255 caracteres']);
            return;
        }

        try {
            $mesa = $this->mesaModel->create($data);

            if (!$mesa) {
                Response::error('Error al crear mesa', 500);
                return;
            }

            Response::success($mesa, 'Mesa creada correctamente', 201);
        } catch (\Exception $error) {
            Response::error('Error al crear mesa: ' . $error->getMessage(), 500);
        }
    }

    public function update($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('Id de mesa inválido');
            return;
        }

        $data = $this->getJsonInput();

        if (!$data) {
            Response::error('Datos JSON inválidos');
            return;
        }

        //Validaciones especificas
        if (isset($data['capacidad']) && (!is_numeric($data['capacidad']) || $data['capacidad'] < 1)) {
            Response::validationError(['capacidad' =>  'La capacidad debe ser un número mayor a 0']);
            return;
        }

        if (isset($data['numero_mesa']) && strlen($data['numero_mesa']) > 5) {
            Response::validationError(['numero_mesa' => 'El número de mesa no puede exceder 5 caracteres']);
            return;
        }

        if (isset($data['nombre_mesa']) && strlen($data['nombre']) > 30) {
            Response::validationError(['nombre_mesa' => 'El nombre de mesa no puede exceder 30 caracteres']);
            return;
        }

        if (isset($data['ubicacion']) && strlen($data['ubicacion']) > 60) {
            Response::validationError(['ubicacion' => 'La ubicación ni puede exceder 60 caracteres']);
            return;
        }

        if (isset($data['caracteristicas']) && strlen($data['caracteristicas']) > 255) {
            Response::validationError(['caracteristicas' => 'Las características no pueden exceder 255 caracteres']);
            return;
        }

        //Validar ENUMs si se envian
        if (isset($data['estado']) && !Mesa::isValidEstado($data['estado'])) {
            Response::validationError(['estado' => 'Estado inválido. Valores permitidos: ' . implode(', ', Mesa::getEstadosValidos())]);
            return;
        }

        if (isset($data['tipo']) && !Mesa::isValidTipo($data['tipo'])) {
            Response::validationError(['tipo' => 'Tipo inválido. Valores permitidos: ' . implode(', ', Mesa::getTiposValidos())]);
            return;
        }

        //Verificar si la mesa existe
        if (!$this->mesaModel->getById($id)) {
            Response::notFound('Mesa no econtrada');
            return;
        }

        //Verificar si el numero de mesa ya existe (excluyendo la mesa actual)
        if (isset($data['numero_mesa']) && $this->mesaModel->numeroMesaExists($data['numero_mesa'], $id)) {
            Response::validationError(['numero_mesa' => 'El número de mesa ya está registrado']);
            return;
        }

        //Limpiar datos
        $data = $this->sanitizeInput($data);

        try {
            $mesa = $this->mesaModel->update($id, $data);

            if (!$mesa) {
                Response::error('Error al actualizar mesa', 500);
                return;
            }

            Response::success($mesa, 'Mesa actualizada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al actualizar mesa: ' . $error->getMessage(), 500);
        }
    }

    //DELETE /mesa/id - Eliminar mesa
    public function delete($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de mesa inválido');
            return;
        }

        if (!$this->mesaModel->getById($id)) {
            Response::notFound('Mesa no econtrada');
            return;
        }

        try {
            $result = $this->mesaModel->delete($id);

            if (!$result) {
                Response::error('Error al eliminar mesa', 500);
                return;
            }

            Response::success(null, 'Mesa eliminada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al eliminar mesa: ' . $error->getMessage(), 500);
        }
    }

    //GET /mesas/disponibles - obtener mesas disponibles con filtros
    public function getAvailable()
    {
        $capacidad = isset($_GET['capacidad']) ? (int)$_GET['capacidad'] : null;
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
        $ubicacion = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : null;

        //// DEBUG: Agregar esto temporalmente
        Debug::log("getAvailable called", $_GET);
        Debug::log("Capacidad procesada", $capacidad);
        Debug::log("Tipo procesada", $tipo);
        Debug::log("Ubicacion procesada", $ubicacion);

        // Validar tipo si se proporciona
        if ($tipo && !Mesa::isValidTipo($tipo)) {
            Response::validationError([
                'tipo' => 'Tipo inválido. Valores permitidos: ' . implode(', ', Mesa::getTiposValidos())
            ]);
            return;
        }

        // Validar capacidad si se proporciona
        if ($capacidad && $capacidad < 1) {
            Response::error('Capacidad inválida');
            return;
        }

        try {
            $mesas = $this->mesaModel->getAvailableWithFilters($capacidad, $tipo, $ubicacion);
            Response::success($mesas, 'Mesas disponibles obtenidas correctamente');
        } catch (\Exception $error) {
            Response::error('Error al obtener mesas disponibles: ' . $error->getMessage(), 500);
        }
    }

    //GET /mesas/tipo/{tipo} - Obtener mesas por tipo
    public function getByType($tipo)
    {
        if (!Mesa::isValidTipo($tipo)) {
            Response::validationError(['tipo' => 'Tipo inválido. Valores permitidos' . implode(', ', Mesa::getTiposValidos())]);
            return;
        }

        try {
            $mesas = $this->mesaModel->getByType($tipo);

            Response::success($mesas, "Mesa tipo $tipo obtenidas correctamente");
        } catch (\Exception $error) {
            Response::error('Error al obtener mesas por tipo: ' . $error->getMessage(), 500);
        }
    }

    //GET /mesa/estado/{estado} - obtener mesas por estado
    public function getByStatus($estado)
    {
        if (!Mesa::isValidEstado($estado)) {
            Response::validationError(['estado' => 'Estado inválido. Valores permiridos: ' . implode(', ', Mesa::getEstadosValidos())]);
            return;
        }

        try {
            $mesas = $this->mesaModel->getByStatus($estado);
            Response::success($mesas, "Mesas con estado $estado obtenidas correctamente");
        } catch (\Exception $error) {
            Response::error('Error al obtener mesas por estado: ' . $error->getMessage(), 500);
        }
    }

    //PUT /mesas/{id}/estado - Cambiar estado de mesa
    public function changeStatus($id)
    {
        if (!$this->isValidId($id)) {
            Response::error('ID de mesa inválido');
            return;
        }

        $data = $this->getJsonInput();

        if (!$data || !isset($data['estado'])) {
            Response::validationError(['estado' => 'Estado inválido. Valores permitidos: ' . implode(', ', Mesa::getEstadosValidos())]);
            return;
        }

        if (!$this->mesaModel->getById($id)) {
            Response::notFound('Mesa no econtrada');
            return;
        }

        try {
            $mesa = $this->mesaModel->changeStatus($id, $data['estado']);

            if (!$mesa) {
                Response::error('Error al cambiar estado de mesa', 500);
                return;
            }

            Response::success($mesa, 'Estado de mesa actualizado correctamente');
        } catch (\Exception $error) {
            Response::error('Error al cambiar estado: ' . $error->getMessage(), 500);
        }
    }

    //GET /mesas/enums - Obtener valores válidos para ENUMs
    public function getEnumValues()
    {
        $enums = [
            'estado' => Mesa::getEstadosValidos(),
            'tipo' => Mesa::getTiposValidos()
        ];

        Response::success($enums, 'Valores ENUM obtenidos correctamente');
    }

    // GET /mesas/disponibilidad?fecha=2025-09-23&hora=14:00:00&capacidad=4
    public function checkDisponibilidad()
    {
        $fecha = $_GET['fecha'] ?? null;
        $hora = $_GET['hora'] ?? null;
        $capacidad = isset($_GET['capacidad']) ? (int)$_GET['capacidad'] : null;
        $tipo = $_GET['tipo'] ?? null;
        $ubicacion = $_GET['ubicacion'] ?? null;

        // Validaciones básicas
        if (!$fecha || !$hora) {
            Response::error('Parámetros fecha y hora son requeridos');
            return;
        }

        if (!$this->validateDate($fecha)) {
            Response::error('Formato de fecha inválido (YYYY-MM-DD)');
            return;
        }

        if (!$this->validateTime($hora)) {
            Response::error('Formato de hora inválido (HH:MM:SS)');
            return;
        }

        try {
            // Obtener todas las mesas que cumplan con los filtros
            $mesas = $this->mesaModel->getAvailableWithFilters($capacidad, $tipo, $ubicacion);

            $mesasDisponibles = [];
            $reservaModel = new \App\Models\Reserva();

            foreach ($mesas as $mesa) {
                $disponible = $reservaModel->checkAvailability(
                    $mesa['id_mesa'],
                    $fecha,
                    $hora
                );

                if ($disponible) {
                    $mesasDisponibles[] = $mesa;
                }
            }

            Response::success([
                'mesas_disponibles' => $mesasDisponibles,
                'total' => count($mesasDisponibles),
                'fecha' => $fecha,
                'hora' => $hora
            ], 'Disponibilidad verificada correctamente');
        } catch (\Exception $error) {
            Response::error('Error al verificar disponibilidad: ' . $error->getMessage(), 500);
        }
    }
}
