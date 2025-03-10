<?php

$dbserver = 'localhost';
$dbname = 'nabila_fashoin_database';
$dbuser = 'root';
$dbpassword = '';
$dsn = "mysql:host={$dbserver};dbname={$dbname}";

$connection = null;

try {

    $connection = new PDO($dsn, $dbuser, $dbpassword);

    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $exception) {

    die("Terjadi error: " . $exception->getMessage());
}

?>