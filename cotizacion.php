<?php
// Configurar cabeceras para respuesta JSON y permitir peticiones web
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

require "CBase.php";

$db = new CBase();
$conn = $db->getConnection();

// Obtener el método de la petición
$method = $_SERVER['REQUEST_METHOD'];

// Manejar diferentes métodos HTTP
switch($method){
    case 'POST':
        // Crear nueva cotización
        $data = json_decode(file_get_contents("php://input"), true);
        
        $id_cliente = $data["id_cliente"] ?? null;
        $id_producto = $data["id_producto"] ?? null;
        $ancho = $data["ancho"] ?? null;
        $largo = $data["largo"] ?? null;
        $cantidad = $data["cantidad"] ?? null;
        
        // Validar que todos los campos obligatorios estén presentes
        if(!$id_cliente || !$id_producto || !$ancho || !$largo || !$cantidad){
            echo json_encode([
                "status" => "error",
                "message" => "Todos los campos son obligatorios."
            ]);
            exit();
        }
        
        // Obtener el precio base del producto desde la base de datos
        $sql_precio = $conn->prepare("SELECT Precio FROM Producto WHERE ID_producto = ?");
        $sql_precio->bind_param("i", $id_producto);
        $sql_precio->execute();
        $result_precio = $sql_precio->get_result();
        
        // Verificar si el producto existe
        if($result_precio->num_rows === 0){
            echo json_encode([
                "status" => "error",
                "message" => "Producto no encontrado."
            ]);
            exit();
        }
        
        // Calcular el precio de la cotización basado en área y cantidad
        $producto = $result_precio->fetch_assoc();
        $precio_base = $producto["Precio"];
        $area = $ancho * $largo; // Calcular área total
        $precio_cotizacion = $precio_base * $area * $cantidad;
        
        // Insertar la cotización en la base de datos (estado Pendiente por defecto)
        $sql = $conn->prepare("INSERT INTO Cotizacion (ID_cliente, ID_producto, Ancho, Largo, Cantidad, PrecioCotizacion, Estado) VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')");
        $sql->bind_param("iiddid", $id_cliente, $id_producto, $ancho, $largo, $cantidad, $precio_cotizacion);
        
        if($sql->execute()){
            // Obtener el ID de la cotización recién creada
            $id_cotizacion = $conn->insert_id;
            
            // Devolver respuesta exitosa con los detalles de la cotización
            echo json_encode([
                "status" => "success",
                "message" => "Cotización creada exitosamente.",
                "cotizacion" => [
                    "id_cotizacion" => $id_cotizacion,
                    "precio_cotizacion" => $precio_cotizacion,
                    "area" => $area
                ]
            ]);
        }else{
            echo json_encode([
                "status" => "error", 
                "message" => "Error al crear cotización: " . $conn->error
            ]);
        }
        break;
        
    case 'GET':
        // Obtener cotizaciones del cliente
        $id_cliente = $_GET['id_cliente'] ?? null;
        
        // Validar que se proporcionó ID cliente
        if(!$id_cliente){
            echo json_encode([
                "status" => "error",
                "message" => "ID cliente es requerido."
            ]);
            exit();
        }
        
        // Consulta para obtener todas las cotizaciones del cliente con información de productos
        $sql = $conn->prepare("
            SELECT c.ID_cotizacion, c.ID_producto, c.Ancho, c.Largo, c.Cantidad, 
                   c.PrecioCotizacion, c.FechaCotizacion, c.Estado,
                   p.TipoMaterial, p.Descripcion, p.Imagen,
                   m.NombreModelo
                FROM Cotizacion c
                INNER JOIN Producto p ON c.ID_producto = p.ID_producto
                INNER JOIN Modelo m ON p.ID_Modelo = m.ID_modelo
                WHERE c.ID_cliente = ?
                ORDER BY c.FechaCotizacion DESC
        ");
        $sql->bind_param("i", $id_cliente);
        $sql->execute();
        $result = $sql->get_result();
        
        $cotizaciones = [];
        // Recorrer todas las cotizaciones y agregarlas al array
        while($row = $result->fetch_assoc()){
            $cotizaciones[] = $row;
        }
        
        // Devolver lista de cotizaciones
        echo json_encode([
            "status" => "success",
            "cotizaciones" => $cotizaciones
        ]);
        break;
        
    default:
        // Método no permitido
        echo json_encode([
            "status" => "error",
            "message" => "Método no permitido."
        ]);
}
?>