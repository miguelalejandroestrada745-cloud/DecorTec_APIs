<?php
// Importar archivos
require "CBase.php";
require "newCola.php";

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // Permite peticiones desde cualquier dominio
header("Access-Control-Allow-Methods: POST, GET, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type"); // Headers permitidos

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ejecución principal del código
try {
    switch ($_SERVER['REQUEST_METHOD']){        
        case "GET":
            // Conexión con la BD
            $db = new CBase();
            $cola = new newCola();
            $idCliente = $_GET['id_cliente'] ?? null;

            // Agregar las cotizaciones de la base de datos a la cola
            $cola->cargarCotizacionesBD($db);
            
            // Obtener la cola con cotizaciones
            $cotizacionesEnCola = $cola->getCola();
            
            if ($idCliente) {
                // Filtrar por ID_cliente
                $cotizacionesEnCola = array_filter($cotizacionesEnCola, function($cotizacion) use ($idCliente) {
                    return $cotizacion['ID_cliente'] == $idCliente;
                });
            }

            // Si hay mínimo una cotización
            if (count($cotizacionesEnCola) > 0) {
                // Enviar respuesta exitosa con cotizaciones en cola
                echo json_encode([
                    "status" => "success",
                    "total_cotizaciones" => count($cotizacionesEnCola),
                    "cotizaciones_en_cola" => $cotizacionesEnCola,
                    "proxima_cotizacion" => $cola->front() // Muestra la próxima a atender
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                // Respuesta cuando no hay cotizaciones
                echo json_encode([
                    "status" => "success",
                    "total_cotizaciones" => 0,
                    "cotizaciones_en_cola" => [],
                    "message" => "No hay cotizaciones en cola para atender."
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            break;

        case "POST":
            // Conexión con la BD
            $db = new CBase();
            $conn = $db->getConnection();
            $cola = new newCola();

            // Obtener los datos JSON de la petición
            $input = json_decode(file_get_contents('php://input'), true);

            // Validar que existan los datos necesarios
            if (!isset($input['ID_cliente']) || !isset($input['ID_producto']) || !isset($input['Cantidad'])) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Faltan campos obligatorios: ID_cliente, ID_producto, Cantidad'
                ]);
                return;
            }

            $id_cliente = filter_var($input['ID_cliente'], FILTER_VALIDATE_INT);
            $id_producto = filter_var($input['ID_producto'], FILTER_VALIDATE_INT);
            $cantidad = filter_var($input['Cantidad'], FILTER_VALIDATE_INT);
            $ancho = isset($input['Ancho']) ? filter_var($input['Ancho'], FILTER_VALIDATE_FLOAT) : null;
            $largo = isset($input['Largo']) ? filter_var($input['Largo'], FILTER_VALIDATE_FLOAT) : null;
            $color = isset($input['Color']) ? htmlspecialchars($input['Color']) : null;
            $id_carrito = isset($input['ID_carrito']) ? $input['ID_carrito'] : null;

            if ($id_cliente === false || $id_producto === false || $cantidad === false || $cantidad <= 0) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Datos inválidos. Las IDs deben ser números enteros y la cantidad mayor a 0'
                ]);
                return;
            }

            // Validar cliente y producto
            $validado = validarClienteProducto($id_cliente, $id_producto);
            if(!$validado['status']){
                if($validado['missing'] === "cliente"){
                    http_response_code(404);
                    echo json_encode([
                        "status" => "error",
                        "message" => "No se encontró al cliente"
                    ]);
                    return;
                }else{
                    http_response_code(404);
                    echo json_encode([
                        "status" => "error",
                        "message" => "No se encontró el producto"
                    ]);
                    return;
                }
            }

            // Insertar cotización
            $result = insertarCotizacion($id_cliente, $id_producto, $cantidad, $ancho, $largo, $color, $id_carrito, $conn);
            
            if ($result['success']) {
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Cotización creada con éxito!',
                    'cotizacion' => [
                        'ID_cotizacion' => $result['id_cotizacion'],
                        'ID_cliente' => $id_cliente,
                        'ID_producto' => $id_producto,
                        'Cantidad' => $cantidad,
                        'Ancho' => $ancho,
                        'Largo' => $largo,
                        'Color' => $color,
                        'PrecioCotizacion' => $result['precio'],
                        'FechaCotizacion' => $result['fecha'],
                        'Estado' => 'Pendiente'
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Error al crear la cotización',
                    'details' => $result['error']
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            break;
    }
} catch (Exception $e) {
    // Manejo de errores
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Error interno del servidor: " . $e->getMessage()
    ]);
} finally {
    // Limpieza y cierre de conexiones
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}

function validarClienteProducto($id_cliente, $id_producto){
    $db = new CBase();
    $conn = $db->getConnection();

    // Validar cliente
    $stmtCliente = $conn->prepare("SELECT ID_cliente FROM Cliente WHERE ID_cliente = ?"); 
    $stmtCliente->bind_param("i", $id_cliente);
    $stmtCliente->execute();
    $resultCliente = $stmtCliente->get_result();
    
    if ($resultCliente->num_rows === 0) {
        $stmtCliente->close();
        return [
            "status" => false,
            "missing" => "cliente"
        ];
    }
    $stmtCliente->close();
    
    // Validar producto
    $stmtProducto = $conn->prepare("SELECT ID_producto, Precio FROM Producto WHERE ID_producto = ?"); 
    $stmtProducto->bind_param("i", $id_producto);
    $stmtProducto->execute();
    $resultProducto = $stmtProducto->get_result();
    
    if ($resultProducto->num_rows === 0) {
        $stmtProducto->close();
        return [
            "status" => false,
            "missing" => "producto"
        ];
    }
    
    $producto = $resultProducto->fetch_assoc();
    $stmtProducto->close();
    
    return [
        "status" => true,
        "precio_producto" => $producto['Precio']
    ];
}

function insertarCotizacion($id_cliente, $id_producto, $cantidad, $ancho, $largo, $color, $id_carrito, $conn){
    try {
        // Obtener precio del producto
        $stmt = $conn->prepare("SELECT Precio FROM Producto WHERE ID_producto = ?"); 
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return [
                'success' => false,
                'error' => 'Producto no encontrado'
            ];
        }

        $row = $result->fetch_assoc();
        $precio_unitario = $row['Precio'];
        $precio_total = $precio_unitario * $cantidad;
        $stmt->close();

        // Preparar ID_carrito como JSON si existe
        $json_carrito = null;
        if ($id_carrito) {
            $json_carrito = json_encode($id_carrito);
        }

        // Crear la cotización en la base de datos
        $stmt2 = $conn->prepare("INSERT INTO Cotizacion (ID_cliente, ID_producto, ID_carrito, Ancho, Largo, Color, Cantidad, PrecioCotizacion, Estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')");
        
        if ($stmt2 === false) {
            return [
                'success' => false,
                'error' => 'Error al preparar la consulta: ' . $conn->error
            ];
        }
        
        $stmt2->bind_param("iissssid", $id_cliente, $id_producto, $json_carrito, $ancho, $largo, $color, $cantidad, $precio_total);
        $success = $stmt2->execute();

        if (!$success) {
            $error = $stmt2->error;
            $stmt2->close();
            return [
                'success' => false,
                'error' => 'Error al insertar cotización: ' . $error
            ];
        }

        $id_cotizacion = $stmt2->insert_id;            
        $stmt2->close();
        
        // Obtener la fecha de creación
        $fecha_cotizacion = date('Y-m-d H:i:s');

        return [
            'success' => true,
            'id_cotizacion' => $id_cotizacion,
            'precio' => $precio_total,
            'fecha' => $fecha_cotizacion
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Excepción: ' . $e->getMessage()
        ];
    }
}
?>