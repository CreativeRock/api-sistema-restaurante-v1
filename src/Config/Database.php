<?php
// src/Config/Database.php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static $server = "localhost";
    private static $dbName = "restaurante_reservas_pruebas_db";
    private static $userName = "root";
    private static $password = "root";
    private static $connection = null;

    // Iniciar la conexión con la base de datos
    public static function init()
    {
        try {
            $dsn = "mysql:host=" . self::$server . ";dbname=" . self::$dbName . ";charset=utf8mb4";

            self::$connection = new PDO($dsn, self::$userName, self::$password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            // AGREGADO: Forzar la selección de la base de datos
            self::$connection->exec("USE " . self::$dbName);
        } catch (PDOException $error) {
            throw new \Exception("Error de conexión a la base de datos: " . $error->getMessage());
        }
    }

    public static function getConnection()
    {
        if (self::$connection === null) {
            self::init();
        }

        return self::$connection;
    }

    public static function close()
    {
        self::$connection = null;
    }
}
