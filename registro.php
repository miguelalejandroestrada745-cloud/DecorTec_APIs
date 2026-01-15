<?php
// Configurar cabeceras para respuesta JSON y permitir peticiones web
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400"); // 24 horas cache para preflight

require "CBase.php";

$db = new CBase();
$conn = $db->getConnection();

// Obtener datos del formulario de registro
$data = json_decode(file_get_contents("php://input"), true);

// Validar que se enviaron datos
if(!$data){
    echo json_encode([
        "status" => "error",
        "message" => "Los datos deben enviarse en formato JSON."
    ]);
    exit();
}

// Obtener todos los campos del formulario
$nombre = $data["nombre"] ?? null;
$apellidoP = $data["apellidoP"] ?? null;
$apellidoM = $data["apellidoM"] ?? null;
$correo = $data["correo"] ?? null;
$password = $data["password"] ?? null;
$telefono = $data["telefono"] ?? null;
$id_direccion = $data["id_direccion"] ?? null;

// Validar campos obligatorios
if(!$nombre || !$apellidoP || !$apellidoM || !$correo || !$password || !$telefono){
    echo json_encode([
        "status" => "error",
        "message" => "Todos los campos son obligatorios."
    ]);
    exit();
}

// Verificar si el correo ya está registrado
$sql_check = $conn->prepare("SELECT ID_cliente FROM Cliente WHERE Correo = ?");
$sql_check->bind_param("s", $correo);
$sql_check->execute();
$result_check = $sql_check->get_result();

if($result_check->num_rows > 0){
    echo json_encode([
        "status" => "error",
        "message" => "El correo ya está registrado."
    ]);
    exit();
}

// ENCRIPTAR CONTRASEÑA de forma segura usando password_hash()
$password_hashed = password_hash($password, PASSWORD_DEFAULT);

// Insertar nuevo cliente en la base de datos
$sql = $conn->prepare("INSERT INTO Cliente (Nombre, ApellidoP, ApellidoM, Correo, Password, Telefono, ID_direccion) VALUES (?, ?, ?, ?, ?, ?, ?)");
$sql->bind_param("ssssssi", $nombre, $apellidoP, $apellidoM, $correo, $password_hashed, $telefono, $id_direccion);

if($sql->execute()){
    $id_cliente = $conn->insert_id;
    echo json_encode([
        "status" => "success",
        "message" => "Registro exitoso.",
        "ID_cliente" => $id_cliente
    ]);
}else{
    echo json_encode([
        "status" => "error",
        "message" => "Error en el registro: " . $conn->error
    ]);
}
?>