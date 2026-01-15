<?php
// Configurar cabeceras para respuesta JSON y permitir peticiones web
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type"); // Headers permitidos

// Incluir la clase de conexión a la base de datos
require "CBase.php";

// Crear instancia de la base de datos y obtener conexión
$db = new CBase();
$conn = $db->getConnection();

// Obtener y decodificar los datos JSON enviados en la petición
$data = json_decode(file_get_contents("php://input"), true);

// Obtener los parámetros de búsqueda con valores por defecto null si no existen
$tipoMaterial = $data["tipoMaterial"] ?? null;
$precioMin = $data["precioMin"] ?? null;
$precioMax = $data["precioMax"] ?? null;
$modelo = $data["modelo"] ?? null;

// Construir consulta base para obtener productos
$sql = "SELECT p.ID_producto, p.TipoMaterial, p.Descripcion, p.Precio, p.Imagen, 
               m.NombreModelo 
        FROM Producto p 
        INNER JOIN Modelo m ON p.ID_Modelo = m.ID_modelo 
        WHERE 1=1"; // WHERE 1=1 para facilitar agregar condiciones con AND
$params = []; // Array para almacenar los parámetros de la consulta
$types = ""; // String para almacenar los tipos de datos de los parámetros

// Agregar filtro por tipo de material si se proporcionó
if($tipoMaterial){
    $sql .= " AND p.TipoMaterial LIKE ?";
    $params[] = "%$tipoMaterial%"; // Buscar parcialmente el tipo de material
    $types .= "s"; // 's' indica que es un string
}

// Agregar filtro por precio mínimo si se proporcionó
if($precioMin){
    $sql .= " AND p.Precio >= ?";
    $params[] = $precioMin;
    $types .= "d"; // 'd' indica que es un double/decimal
}

// Agregar filtro por precio máximo si se proporcionó
if($precioMax){
    $sql .= " AND p.Precio <= ?";
    $params[] = $precioMax;
    $types .= "d";
}

// Agregar filtro por modelo si se proporcionó
if($modelo){
    $sql .= " AND m.NombreModelo LIKE ?";
    $params[] = "%$modelo%"; // Buscar parcialmente el nombre del modelo
    $types .= "s";
}

// Preparar la consulta SQL
$stmt = $conn->prepare($sql);

// Si hay parámetros, vincularlos a la consulta
if(!empty($params)){
    $stmt->bind_param($types, ...$params);
}

// Ejecutar la consulta
$stmt->execute();
$result = $stmt->get_result();

// Verificar si se encontraron resultados
if($result->num_rows > 0){
    $productos = [];
    // Recorrer cada fila y agregarla al array de productos
    while($row = $result->fetch_assoc()){
        $productos[] = $row;
    }
    
    // Devolver respuesta exitosa con los productos encontrados
    echo json_encode([
        "status" => "success",
        "productos" => $productos,
        "total" => $result->num_rows
    ]);
}else{
    // Devolver respuesta de error si no se encontraron productos
    echo json_encode([
        "status" => "error",
        "message" => "No se encontraron productos con los filtros aplicados."
    ]);
}
?>