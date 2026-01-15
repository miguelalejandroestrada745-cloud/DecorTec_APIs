<?php
require "CBase.php";

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==================== CLASE COLA ====================
class Cola {
    private $pedidos = [];

    public function enqueue($pedido) {
        array_push($this->pedidos, $pedido);
    }

    public function dequeue() {
        if ($this->isEmpty()) {
            return null;
        }
        return array_shift($this->pedidos);
    }

    public function front() {
        return $this->isEmpty() ? null : $this->pedidos[0];
    }

    public function isEmpty() {
        return count($this->pedidos) === 0;
    }

    public function size() {
        return count($this->pedidos);
    }

    public function getCola() {
        return $this->pedidos;
    }

    public function cargarPedidosBD($database) {
        try {
            $conn = $database->getConnection();

            $sql = "SELECT ped.ID_pedido, cli.ID_cliente AS ID_cliente, CONCAT(cli.Nombre, ' ', cli.ApellidoP, ' ', cli.ApellidoM) AS Cliente,
                CASE 
                    WHEN c.ID_producto IS NOT NULL THEN p.Descripcion
                    ELSE (
                        SELECT GROUP_CONCAT(pr.Descripcion SEPARATOR ', ')
                        FROM Carrito car
                        JOIN Producto pr ON car.ID_producto = pr.ID_producto
                        WHERE FIND_IN_SET(car.ID_carrito, REPLACE(REPLACE(REPLACE(c.ID_carrito, '[', ''), ']', ''), '\"', ''))
                        AND car.Estado = 'Cotizado'
                    )
                END AS Productos,

                CASE 
                    WHEN c.ID_producto IS NOT NULL THEN c.Ancho
                    ELSE (
                        SELECT GROUP_CONCAT(car.Ancho SEPARATOR ', ')
                        FROM Carrito car
                        WHERE FIND_IN_SET(car.ID_carrito, REPLACE(REPLACE(REPLACE(c.ID_carrito, '[', ''), ']', ''), '\"', ''))
                        AND car.Estado = 'Cotizado'
                    )
                END AS Ancho,
                
                CASE 
                    WHEN c.ID_producto IS NOT NULL THEN c.Largo
                    ELSE (
                        SELECT GROUP_CONCAT(car.Largo SEPARATOR ', ')
                        FROM Carrito car
                        WHERE FIND_IN_SET(car.ID_carrito, REPLACE(REPLACE(REPLACE(c.ID_carrito, '[', ''), ']', ''), '\"', ''))
                        AND car.Estado = 'Cotizado'
                    )
                END AS Largo,
                
                CASE 
                    WHEN c.ID_producto IS NOT NULL THEN c.Color
                    ELSE (
                        SELECT GROUP_CONCAT(car.Color SEPARATOR ', ')
                        FROM Carrito car
                        WHERE FIND_IN_SET(car.ID_carrito, REPLACE(REPLACE(REPLACE(c.ID_carrito, '[', ''), ']', ''), '\"', ''))
                        AND car.Estado = 'Cotizado'
                    )
                END AS Color,

                ped.Total AS Precio,
                ped.Estado,
                c.FechaCotizacion AS Fecha,
                CONCAT(d.CallePrincipal, ' entre ', d.Cruzamiento1, ' y ', d.Cruzamiento2, ', ', d.CodigoPostal) AS Direccion 

                FROM Pedido ped
                LEFT JOIN Cotizacion c ON ped.ID_cotizacion = c.ID_cotizacion
                INNER JOIN Cliente cli ON c.ID_cliente = cli.ID_cliente
                LEFT JOIN Producto p ON c.ID_producto = p.ID_producto
                LEFT JOIN Direccion d ON cli.ID_direccion = d.ID_direccion
                WHERE c.Estado IN ('Aprobada', 'Modificada')
                ORDER BY c.FechaCotizacion ASC;
                ";

            $stmt = $conn->prepare($sql);
            if (!$stmt->execute()) {
                throw new Exception("Error en la consulta: " . $stmt->error);
            }

            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $row['posicion_cola'] = count($this->pedidos) + 1;
                $this->enqueue($row);
            }

            $stmt->close();
            return true;

        } catch (Exception $e) {
            throw new Exception("Error al cargar pedidos: " . $e->getMessage());
        }
    }
}

// ==================== FUNCIONES ====================
function validarIDs($id_cliente, $id_cotizacion) {
    $db = new CBase();
    $conn = $db->getConnection();

    $stmt2 = $conn->prepare("SELECT ID_cliente FROM Cliente WHERE ID_cliente = ?");
    $stmt2->bind_param("i", $id_cliente);
    $stmt2->execute();
    $result = $stmt2->get_result();

    if ($result->num_rows === 0) {
        $stmt2->close();
        return [
            "status" => false,
            "missing" => "cliente"
        ];
    }
    $stmt2->close();

    $stmt = $conn->prepare("SELECT PrecioCotizacion FROM Cotizacion WHERE ID_cotizacion = ?");
    $stmt->bind_param("i", $id_cotizacion);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return [
            "status" => false,
            "missing" => "cotizacion"
        ];
    }
    $stmt->close();

    return [
        "status" => true
    ];
}

function insertarPedido($id_cliente, $id_cotizacion, $conn) {
    $stmt = $conn->prepare("SELECT PrecioCotizacion FROM Cotizacion WHERE ID_cotizacion = ?");
    $stmt->bind_param("i", $id_cotizacion);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return false;
    }

    $row = $result->fetch_assoc();
    $precio = $row['PrecioCotizacion'];
    $stmt->close();

    $stmt2 = $conn->prepare("INSERT INTO Pedido (ID_cliente, ID_cotizacion, Total, Estado) VALUES (?, ?, ?, 'Pendiente')");
    $stmt2->bind_param("iid", $id_cliente, $id_cotizacion, $precio);
    $success = $stmt2->execute();

    if (!$success) {
        $error = $stmt2->error;
        $stmt2->close();
        return false;
    }

    $id_pedido = $stmt2->insert_id;
    $stmt2->close();
    $fecha_pedido = date('Y-m-d H:i:s');

    return [
        'success' => true,
        'total' => $precio,
        'fecha' => $fecha_pedido,
        'id_pedido' => $id_pedido,
    ];
}

// ==================== EJECUCIÓN PRINCIPAL ====================
try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case "GET":
            $db = new CBase();
            $cola = new Cola();
            $idCliente = $_GET['id_cliente'] ?? null;

            $cola->cargarPedidosBD($db);

            $pedidosEnCola = $cola->getCola();
            if ($idCliente) {
                $pedidosEnCola = array_filter($pedidosEnCola, function ($pedido) use ($idCliente) {
                    return $pedido['ID_cliente'] == $idCliente;
                });
                $pedidosEnCola = array_values($pedidosEnCola);
            }

            if (count($pedidosEnCola) > 0) {
                echo json_encode([
                    "status" => "success",
                    "total_pedidos" => count($pedidosEnCola),
                    "pedidos_en_cola" => $pedidosEnCola,
                    "proximo_pedido" => $cola->front()
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    "status" => "success",
                    "total_pedidos" => 0,
                    "pedidos_en_cola" => [],
                    "message" => "No hay pedidos en cola para atender."
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            break;

        case "POST":
            $db = new CBase();
            $conn = $db->getConnection();
            $cola = new Cola();

            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['ID_cliente']) || !isset($input['ID_cotizacion'])) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Faltan campos obligatorios: ID_cliente, ID_cotizacion'
                ]);
                exit();
            }

            $id_cliente = filter_var($input['ID_cliente'], FILTER_VALIDATE_INT);
            $id_cotizacion = filter_var($input['ID_cotizacion'], FILTER_VALIDATE_INT);

            if ($id_cliente === false || $id_cotizacion === false) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Datos invalidos. Las IDs deben ser numeros enteros'
                ]);
                exit();
            }

            $validado = validarIDs($id_cliente, $id_cotizacion);
            if (!$validado['status']) {
                http_response_code(404);
                if ($validado['missing'] === "cliente") {
                    echo json_encode([
                        "status" => "error",
                        "message" => "No se encontro al cliente"
                    ]);
                } else {
                    echo json_encode([
                        "status" => "error",
                        "message" => "No se encontro la cotizacion"
                    ]);
                }
                exit();
            }

            $result = insertarPedido($id_cliente, $id_cotizacion, $conn);
            if (!$result) {
                http_response_code(404);
                echo json_encode([
                    "status" => "error",
                    "message" => "No se encontro la cotizacion"
                ]);
            } else if ($result['success']) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Pedido creado con éxito!',
                    'pedido' => [
                        'ID_cliente' => $id_cliente,
                        'ID_cotizacion' => $id_cotizacion,
                        'Total' => $result['total'],
                        'FechaPedido' => $result['fecha']
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al crear el pedido']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["error" => "Método no permitido"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Error interno del servidor: " . $e->getMessage()
    ]);
}
?>
