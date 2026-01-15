<?php
// Configurar cabeceras para respuesta JSON y permitir peticiones web
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

// Incluir la clase de conexión (usando require_once para evitar duplicados)
require_once "CBase.php";

$db = new CBase();
$conn = $db->getConnection();

// Consulta para obtener todos los productos con información del modelo asociado
$sql = "SELECT p.ID_producto, p.TipoMaterial, p.Descripcion, p.Precio, p.Imagen, 
               m.NombreModelo, m.Descripcion as DescripcionModelo 
        FROM Producto p 
        INNER JOIN Modelo m ON p.ID_Modelo = m.ID_modelo";

$result = $conn->query($sql);

if($result->num_rows > 0){
    $productos = [];
    // Recorrer todos los productos y agregarlos al array
    while($row = $result->fetch_assoc()){
        $productos[] = $row;
    }
    
    // Devolver catálogo completo de productos
    echo json_encode([
        "status" => "success",
        "productos" => $productos
    ]);
}else{
    echo json_encode([
        "status" => "error",
        "message" => "No se encontraron productos."
    ]);
}
?>