<?php
require_once 'auth.php';
header("Access-Control-Allow-Origin: *"); // Permite acceso desde cualquier subdominio
header("Content-Type: application/json; charset=utf-8");

$servername = "localhost";
$username = "tabolang_app";
$password = 'm{Hpj.?IZL$Kz${S';
$dbname = "tabolang_pedidos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die(json_encode([])); }
$conn->set_charset("utf8mb4");

// Usamos alias 'nombre' para simplificar el JS
$sql = "SELECT CONCAT(nombre, ' ', apellido) AS nombre, LOWER(TRIM(email)) AS email 
        FROM app_usuarios 
        WHERE activo = 1";

$result = $conn->query($sql);
$usuarios = [];
while($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

echo json_encode($usuarios);
$conn->close();
?>