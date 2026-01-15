<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require "CBase.php";

    class ApiCarritoPila {
        private $table = "Carrito";
        private $conexion;

        public function __construct(){
            $db = new CBase();
            $this->conexion = $db->getConnection();
        }

        // GET: listar todos los productos del carrito
        public function getAll($ID_cliente){
            if(!$ID_cliente){
                http_response_code(400);
                return ["status"=>"error","message"=>"Falta ID_cliente en la petición."];
            }

            $sql = $this->conexion->prepare("SELECT c.ID_carrito, c.ID_cliente, m.Descripcion as Nombre, c.ID_producto, c.Ancho, c.Largo, 
            c.Color, p.Precio, p.Imagen, c.Estado, c.FechaAgregado FROM {$this->table} c
            JOIN producto p ON c.ID_producto = p.ID_producto
            JOIN Modelo m ON p.ID_Modelo = m.ID_modelo
            WHERE ID_cliente = ? AND estado = 'Activo' ORDER BY FechaAgregado DESC");

            $sql->bind_param("i", $ID_cliente);
            if(!$sql->execute()){
                http_response_code(500);
                return ["status"=>"error","message"=>"Error al ejecutar la consulta: ".$this->conexion->error];
            }
            $result = $sql->get_result();
            if($result->num_rows === 0){
                http_response_code(200);
                return ["status"=>"success","message"=>"El carrito está vacío."];
            }
            $items = [];
            while($row = $result->fetch_assoc()) $items[] = $row;

            http_response_code(200);
            return ["status"=>"success","data"=>$items];
        }

        // GET action=peek-ver el elemento en la cima
        public function peek($ID_cliente){
            if(!$ID_cliente){
                http_response_code(400);
                return ["status"=>"error","message"=>"Falta ID_cliente en la petición."];
            }
            $sql = $this->conexion->prepare("SELECT ID_carrito, ID_cliente, ID_producto, Ancho, Largo, Color, Precio, FechaAgregado FROM {$this->table} WHERE ID_cliente = ? ORDER BY FechaAgregado DESC LIMIT 1");
            $sql->bind_param("i", $ID_cliente);
            if(!$sql->execute()){
                http_response_code(500);
                return ["status"=>"error","message"=>"Error al ejecutar la consulta: ".$this->conexion->error];
            }
            $res = $sql->get_result();
            if($res->num_rows === 0){
                http_response_code(404);
                return ["status"=>"not found","message"=>"No hay artículos arriba de la pila para ese cliente."];
            }
            $row = $res->fetch_assoc();
            http_response_code(200);
            return ["status"=>"success","data"=>$row];
        }

        //MODIFICADA por Morfin
        // POST: push- crear Cotizacion con carritos
            public function push($data){
                try {
                    if(!$data){
                        http_response_code(400);
                        return ["status"=>"error","message"=>"La petición debe enviarse en JSON."];
                    }

                    $ID_cliente = $data["ID_cliente"] ?? null;
                    $ID_carritos = $data["ID_carritos"] ?? null;

                    // Crear placeholders para la consulta IN
                    $placeholders = implode(',', array_fill(0, count($ID_carritos), '?'));
                    
                    // SOLO SUMAR el campo Precio (que ya está calculado en carrito)
                    $sql = $this->conexion->prepare("SELECT SUM(Precio) as total 
                        FROM carrito WHERE ID_carrito IN ($placeholders)");
                    
                    // Bind parameters dinámicamente
                    $types = str_repeat('i', count($ID_carritos));
                    $sql->bind_param($types, ...$ID_carritos);
                    
                    $sql->execute();
                    $result = $sql->get_result();
                    $row = $result->fetch_assoc();
                    $sql->close();

                    $sql_update = $this->conexion->prepare("UPDATE carrito SET estado = 'Cotizado' WHERE ID_carrito IN ($placeholders)");
                    
                    $sql_update->bind_param($types, ...$ID_carritos);
                    
                    if(!$sql_update->execute()){
                        throw new Exception("Error al actualizar estado de carritos: " . $this->conexion->error);
                    }
                    
                    $sql_update->close();
                    
                    $precio = $row['total'] ?? 0;


                    $cantidad = count($ID_carritos);
                    $fecha = new DateTime('now', new DateTimeZone('America/Cancun'));
                    $fechaString = $fecha->format('Y-m-d H:i:s');
                    // Convertir array a JSON string para la BD
                    $idsCarritoJSON = json_encode($ID_carritos);

                    

                    if(!$ID_cliente || !$ID_carritos){
                        http_response_code(400);
                        return ["status"=>"error","message"=>"Todos los campos son obligatorios."];
                    }

                    $sql = $this->conexion->prepare("INSERT into cotizacion (ID_cliente, ID_carrito, Cantidad, PrecioCotizacion, FechaCotizacion, Estado)
                                                    VALUES (?, ?, ?, ?, ?, 'Aprobada')");

                    $sql->bind_param("isiis", $ID_cliente, $idsCarritoJSON, $cantidad, $precio, $fechaString);

                    if(!$sql->execute()){
                        http_response_code(500);
                        return ["status"=>"error","message"=>"No se pudo agregar el artículo: ".$this->conexion->error];
                    }

                    $ID_cotizacion = $this->conexion->insert_id;
                    $sql->close();

                    $fourIds = array_slice($ID_carritos, 0, 4);
                    $placeholders = implode(',', array_fill(0, count($fourIds), '?'));

                    $stmt = $this->conexion->prepare("SELECT Imagen FROM carrito c 
                        JOIN producto p ON c.ID_producto = p.ID_producto 
                        WHERE c.ID_carrito IN ($placeholders) 
                        LIMIT 4");
                    
                    $types = str_repeat('i', count($ID_carritos));
                    $stmt->bind_param($types, ...$fourIds);

                    $stmt->execute();
                    $result = $stmt->get_result();

                    $imagenes = [];
                    while ($row = $result->fetch_assoc()) {
                        if (!empty($row['Imagen'])) {
                            $imagenes[] = $row['Imagen'];
                        }
                    }
                    $stmt->close();

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Cotización creada exitosamente',
                        'data' => [
                            'id_cotizacion' => $ID_cotizacion,
                            'precio_total' => $precio,
                            'fecha' => $fechaString,
                            'estado' => 'Pendiente',
                            'cantidad_productos' => $cantidad,
                            'imagenes' => $imagenes
                        ]
                    ]);
                    exit;
                }catch (Exception $e){
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Error: ' . $e->getMessage()
                    ]);
                    exit;
                }
            }

        // DELETE: eliminar el último artículo 
        // Puede recibir ID_cliente por medio del query
        public function pop($ID_cliente){
            if(!$ID_cliente){
                http_response_code(400);
                return ["status"=>"error","message"=>"Falta ID_cliente en la petición."];
            }

            $this->conexion->begin_transaction();

            $select = $this->conexion->prepare("SELECT ID_carrito, ID_cliente, ID_producto, Ancho, Largo, Color, Precio, FechaAgregado FROM {$this->table} WHERE ID_cliente = ? ORDER BY FechaAgregado DESC LIMIT 1");
            $select->bind_param("i", $ID_cliente);
            if(!$select->execute()){
                $this->conexion->rollback();
                http_response_code(500);
                return ["status"=>"error","message"=>"Error al leer el elemento: ".$this->conexion->error];
            }
            $res = $select->get_result();
            if($res->num_rows === 0){
                $this->conexion->rollback();
                http_response_code(404);
                return ["status"=>"not found","message"=>"No hay elementos que eliminar."];
            }
            $item = $res->fetch_assoc();
            $id_carrito = $item["ID_carrito"];

            $del = $this->conexion->prepare("DELETE FROM {$this->table} WHERE ID_carrito = ?");
            $del->bind_param("i", $id_carrito);
            if(!$del->execute()){
                $this->conexion->rollback();
                http_response_code(500);
                return ["status"=>"error","message"=>"Error al eliminar: ".$this->conexion->error];
            }

            $this->conexion->commit();
            http_response_code(200);
            return ["status"=>"success","message"=>"Elemento eliminado correctamente.","data"=>$item];
        }
    }

    $api = new ApiCarritoPila();
    $method = $_SERVER["REQUEST_METHOD"];

        if($method === "OPTIONS"){
            http_response_code(200);
            exit();
        }

        if($method === "GET"){
            // GET listar
            // GET: ver por encima
            $ID_cliente = isset($_GET["ID_cliente"]) ? intval($_GET["ID_cliente"]) : null;
            $action = $_GET["action"] ?? null;
            if($action === "peek"){
                echo json_encode($api->peek($ID_cliente), JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode($api->getAll($ID_cliente), JSON_UNESCAPED_UNICODE);
            }
        } else if($method === "POST"){
            $data = json_decode(file_get_contents("php://input"), true);
            echo json_encode($api->push($data), JSON_UNESCAPED_UNICODE);
        } else if($method === "DELETE"){

            // DELETE para borrar accion
            $body = json_decode(file_get_contents("php://input"), true);
            $ID_cliente = $body["ID_cliente"] ?? (isset($_GET["ID_cliente"]) ? intval($_GET["ID_cliente"]) : null);
            echo json_encode($api->pop($ID_cliente), JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(405);
            echo json_encode(["status"=>"error","message"=>"No permitido."]);
        }
?>
