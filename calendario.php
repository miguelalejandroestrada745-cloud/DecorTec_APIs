<?php
// Configurar cabeceras para respuesta JSON y permitir peticiones web
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

require "CBase.php";

$db = new CBase();
$conn = $db->getConnection();

// Obtener el método de la petición (GET o POST)
$method = $_SERVER['REQUEST_METHOD'];

// Si es GET, obtener las fechas de instalación
if($method == 'GET'){
    // Consulta para obtener todas las fechas de instalación con información de pedidos y clientes
    $sql = "SELECT fi.ID_fechaInstalacion, fi.FechaInstalacion, fi.Disponible, 
                   p.ID_pedido, c.Nombre, c.ApellidoP
            FROM FechaInstalacion fi
            LEFT JOIN Pedido p ON fi.ID_pedido = p.ID_pedido
            LEFT JOIN Cliente c ON p.ID_cliente = c.ID_cliente
            ORDER BY fi.FechaInstalacion";
    
    $result = $conn->query($sql);
    
    if($result->num_rows > 0){
        $fechas = [];
        while($row = $result->fetch_assoc()){
            $fechas[] = $row;
        }
        
        echo json_encode([
            "status" => "success",
            "fechas" => $fechas
        ]);
    }else{
        echo json_encode([
            "status" => "error",
            "message" => "No hay fechas de instalación registradas."
        ]);
    }
}
// Si es POST, agregar una nueva fecha de instalación
else if($method == 'POST'){
    // Obtener y decodificar los datos JSON enviados
    $data = json_decode(file_get_contents("php://input"), true);
    
    $fecha_instalacion = $data["fecha_instalacion"] ?? null;
    $id_pedido = $data["id_pedido"] ?? null;
    
    // Validar que se proporcionaron los datos requeridos
    if(!$fecha_instalacion || !$id_pedido){
        echo json_encode([
            "status" => "error",
            "message" => "Fecha de instalación e ID pedido son requeridos."
        ]);
        exit();
    }
    
    // Insertar nueva fecha de instalación (Disponible = FALSE porque ya está ocupada)
    $sql = $conn->prepare("INSERT INTO FechaInstalacion (ID_pedido, FechaInstalacion, Disponible) VALUES (?, ?, FALSE)");
    $sql->bind_param("is", $id_pedido, $fecha_instalacion);
    
    if($sql->execute()){
        echo json_encode([
            "status" => "success",
            "message" => "Fecha de instalación agendada exitosamente."
        ]);
    }else{
        echo json_encode([
            "status" => "error",
            "message" => "Error al agendar fecha: " . $conn->error
        ]);
    }
}
?>