<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once "CBase.php";

$db = new CBase();
$conn = $db->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === "OPTIONS") {
    http_response_code(200);
    exit();
}

switch ($method) {

    // GET 
    case "GET":
        if (!isset($_GET["ID_cliente"])) {
            echo json_encode([
                "success" => false,
                "message" => "ID_cliente no proporcionado"
            ]);
            exit();
        }

        $id = intval($_GET["ID_cliente"]);

        $query = $conn->prepare("
            SELECT 
                ID_cliente, 
                Nombre, 
                ApellidoP, 
                ApellidoM, 
                Correo, 
                Telefono 
            FROM Cliente 
            WHERE ID_cliente = ?
        ");
        $query->bind_param("i", $id);
        $query->execute();

        $result = $query->get_result();

        if ($result->num_rows === 0) {
            echo json_encode([
                "success" => false,
                "message" => "Cliente no encontrado"
            ]);
            exit();
        }

        echo json_encode([
            "success" => true,
            "data" => $result->fetch_assoc()
        ]);
        break;


    // PUT
    case "PUT":

        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!$data) {
            echo json_encode([
                "success" => false,
                "message" => "JSON inválido"
            ]);
            exit();
        }

        if (!isset($data["ID_cliente"])) {
            echo json_encode([
                "success" => false,
                "message" => "ID_cliente es requerido"
            ]);
            exit();
        }

        $query = $conn->prepare("
            UPDATE Cliente SET 
                Nombre = ?, 
                ApellidoP = ?, 
                ApellidoM = ?, 
                Correo = ?, 
                Telefono = ?
            WHERE ID_cliente = ?
        ");

        $query->bind_param(
            "sssssi",
            $data["Nombre"],
            $data["ApellidoP"],
            $data["ApellidoM"],
            $data["Correo"],
            $data["Telefono"],
            $data["ID_cliente"]
        );

        if ($query->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Perfil actualizado correctamente"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Error al actualizar",
                "error" => $conn->error
            ]);
        }

        break;

    default:
        echo json_encode([
            "success" => false,
            "message" => "Método no permitido"
        ]);
        break;
}
?>
