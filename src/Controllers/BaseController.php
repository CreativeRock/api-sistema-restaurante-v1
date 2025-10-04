<?php

namespace App\Controllers;

use App\Utils\Response;

class BaseController
{
    //Obtener datos JSON
    protected function getJsonInput()
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }

    //Validar que los campoes requeridos esten
    protected function validateRequerid($data, $required)
    {
        $errors = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "El campo $field es requerido";
            }
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        return true;
    }

    protected function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }

        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    protected function isValidId($id)
    {
        return is_numeric($id) && $id > 0;
    }

    protected function isValidMesaId($id)
    {
        return is_numeric($id) && $id > 0;
    }

    protected function validateTime($time)
    {
        $dateTimeObject = \DateTime::createFromFormat('H:i:s', $time);
        return $dateTimeObject && $dateTimeObject->format('H:i:s') === $time;
    }

    protected function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    //Validar múltiples formatos
    protected function validateDateMultiple($date)
    {
        $formats = ['Y-m-d', 'Y/m/d', 'd-m-Y', 'd/m/Y', 'm-d-Y', 'm/d/Y'];

        foreach ($formats as $format) {
            if ($this->validateDate($date, $format)) {
                return true;
            }
        }

        return false;
    }
}
