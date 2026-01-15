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

// Obtener credenciales del usuario desde la petición
$data = json_decode(file_get_contents("php://input"), true);

$correo = $data["correo"] ?? null;
$password = $data["password"] ?? null;

// Validar que se proporcionaron credenciales
if(!$correo || !$password){
    echo json_encode([
        "status" => "error",
        "message" => "El correo y password son requeridos."
    ]);
    exit();
}

// Buscar usuario por correo electrónico
$sql = $conn->prepare("SELECT ID_cliente, Nombre, ApellidoP, ApellidoM, Correo, Password, Telefono FROM Cliente WHERE Correo = ? LIMIT 1");
$sql->bind_param("s", $correo);
$sql->execute();
$result = $sql->get_result();

// Verificar si el usuario existe
if($result->num_rows === 0){
    echo json_encode([
        "status" => "error",
        "message" => "El usuario no está registrado."
    ]);
    exit();
}

$user = $result->fetch_assoc();

// VERIFICACIÓN SEGURA DE CONTRASEÑA usando password_verify()
if(!password_verify($password, $user["Password"])){
    echo json_encode([
        "status" => "error",
        "message" => "La contraseña es incorrecta."
    ]);
    exit();
}

// Login exitoso - devolver información del usuario (sin la contraseña)
echo json_encode([
    "status" => "success",
    "message" => "Login exitoso.",
    "user" => [
        "ID_cliente" => $user["ID_cliente"],
        "Nombre" => $user["Nombre"],
        "ApellidoP" => $user["ApellidoP"],
        "ApellidoM" => $user["ApellidoM"],
        "Correo" => $user["Correo"],
        "Telefono" => $user["Telefono"]
    ]
]);
?>