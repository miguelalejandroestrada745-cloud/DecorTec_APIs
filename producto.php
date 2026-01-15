<?php
// Configurar cabeceras para respuesta JSON y permitir peticiones web
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type"); // Headers permitidos

// Incluir la clase de conexión (usando require_once para evitar duplicados)
require_once "CBase.php";

$db = new CBase();
$conn = $db->getConnection();


$method = $_SERVER['REQUEST_METHOD'];
    try{
        switch ($method){
            case 'GET':
                $id_producto = null;

                // Primero intentar obtener de GET
                if(isset($_GET['id']) && $_GET['id'] !== ''){
                    $id_producto = intval($_GET['id']);
                }
                // Verificar si se proporcionó un ID válido
                if($id_producto === null || $id_producto <= 0){
                    echo json_encode([
                        "status" => "error",
                        "message" => "Debe proporcionar un ID de producto válido.",
                        "recibido" => [
                            "GET" => $_GET
                        ]
                    ]);
                    exit;
                }
                // Consulta para obtener UN producto específico con información del modelo asociado
                $sql = "SELECT p.ID_producto, p.TipoMaterial, p.Descripcion, p.Precio, p.Imagen, 
                            m.NombreModelo, m.Descripcion as DescripcionModelo 
                        FROM Producto p 
                        INNER JOIN Modelo m ON p.ID_Modelo = m.ID_modelo
                        WHERE p.ID_producto = ?
                        LIMIT 1";

                // Usar prepared statement para prevenir inyección SQL
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id_producto);
                $stmt->execute();
                $result = $stmt->get_result();

                if($result->num_rows > 0){
                    $row = $result->fetch_assoc();
                    
                    // Devolver solo un producto
                    echo json_encode([
                        "status" => "success",
                        "producto" => [
                            "ID_producto" => $row["ID_producto"],
                            "TipoMaterial" => $row["TipoMaterial"],
                            "Descripcion" => $row["Descripcion"],
                            "Precio" => $row["Precio"],
                            "Imagen" => $row["Imagen"],
                            "NombreModelo" => $row["NombreModelo"],
                            "ModeloDescripcion" => $row["DescripcionModelo"]
                        ]
                    ]);
                } else {
                    echo json_encode([
                        "status" => "error",
                        "message" => "No se encontró el producto con ID: " . $id_producto
                    ]);
                }

                $stmt->close();
                $conn->close();
                break;

            case 'POST':

                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
                if (strpos($contentType, 'application/json') !== false) {
                    // Si es JSON
                    $input = json_decode(file_get_contents('php://input'), true);
                } else {
                    // Si es form-data
                    $input = $_POST;
                }

                $datos = [
                    'Ancho' => $input['ancho'] ?? null,
                    'Largo' => $input['largo'] ?? null,
                    'Color' => $input['color'] ?? null,
                    'ID_producto' => $input['id_producto'] ?? null,
                    'ID_cliente' => $input['id_cliente'] ?? null
                ];

                $fecha = new DateTime('now', new DateTimeZone('America/Cancun'));
                $fechaString = $fecha->format('Y-m-d H:i:s');

                //Crear la sentencia SQL de insercion 
                $sql = "INSERT into carrito (ID_cliente, ID_producto, Ancho, Largo, Color, Precio, FechaAgregado)
                VALUES (?, ?, ?, ?, ?, 1, ?)";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiddss", $datos['ID_cliente'], $datos['ID_producto'], $datos['Ancho'], $datos['Largo'], $datos['Color'], $fechaString);
                
                //Insertar el registro en la base de datos
                if($stmt->execute()){
                    $idCarrito = $conn->insert_id;
                }else{
                    throw new Exception("Error al agregar al carrito");
                }

                $stmt->close();

                $query = $conn->prepare("SELECT Precio FROM Producto WHERE ID_producto = ?");
                $query->bind_param("i", $datos['ID_producto']);

                if($query->execute()){
                    $result = $query->get_result();
                    if ($result->num_rows > 0){
                        $row = $result->fetch_assoc();
                        $precio = $row['Precio'];
                    } else{
                        throw new Exception("No se encontró el producto.");
                    }
                }else{
                    throw new Exception("Ocurrió un error en la consulta.");
                }

                $result->close();
                $query->close();
                
                $precioCalculado = $datos['Ancho'] * $datos['Largo'] * $precio;

                $query2 = $conn->prepare("UPDATE carrito SET Precio = ? WHERE ID_Carrito = ?");
                $query2->bind_param("di", $precioCalculado, $idCarrito);

                if($query2->execute()){
                    if ($conn->affected_rows > 0){
                        $query2->close();
                        echo json_encode([
                        "success" => true,
                        "message" => "Producto agregado al carrito."
                        ]);
                    } else{
                        $query2->close();
                        echo json_encode([
                            "status" => "error",
                            "message" => "El producto no se agregó correctamente."
                        ]);
                    }
                }else{
                    echo json_encode([
                        "status" => "error",
                        "message" => "Ocurrió un error en la consulta"
                    ]);
                }
                break;

            default:
                echo json_encode([
                        "status" => "error",
                        "message" => "Acción no valida"
                    ]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }


?>