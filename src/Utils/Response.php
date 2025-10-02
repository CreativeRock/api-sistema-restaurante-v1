<?php

namespace App\Utils;

class Response
{
    //Respuesta exitosa
    public static function success($data = null, $message = 'Operación exitosa', $code = 200)
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    //Respuesta de error
    public static function error($message = 'Error en la operacion', $code = 400, $details = null)
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'details' => $details
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    //Respuesta no econtrada
    public static function notFound($message = 'Recurso no econtrado')
    {
        self::error($message, 404);
    }

    //Respuesta no autorizado
    public static function unauthorized($message = 'No autorizado')
    {
        self::error($message, 401);
    }

    //Respuesta prohibido
    public static function forbidden($message = 'Acceso prohibido')
    {
        self::error($message, 403);
    }

    //Validacion fallida
    public static function validationError($errors)
    {
        self::error('Errores de validación', 422, $errors);
    }

    //Conflicos recursos duplicados
    public static function conflict($message = 'Conflicto con recurso existente')
    {
        self::error($message, 409);
    }
}