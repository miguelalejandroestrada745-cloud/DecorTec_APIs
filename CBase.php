<?php
class CBase {
    private $db_host = "bd6nwozkfuy3bx0r6czs-mysql.services.clever-cloud.com";
    private $db_user = "uvrln1kgonttshoc";
    private $db_password = "BmR9D1le4UryBaTkij03";
    private $db_name = "bd6nwozkfuy3bx0r6czs";
    private $db_port = 3306;
    private $charset = "utf8";
    private $connection;

    public function __construct(){
        // Desactivar el reporte de excepciones automático para manejarlo manualmente
        mysqli_report(MYSQLI_REPORT_OFF);

        $this->connection = @new mysqli(
            $this->db_host,
            $this->db_user,
            $this->db_password,
            $this->db_name,
            $this->db_port
        );

        if($this->connection->connect_errno){
            error_log("Connection failed: " . $this->connection->connect_error);
            echo json_encode([
                "status" => "error",
                "message" => "Error de conexión a la base de datos: " . $this->connection->connect_error
            ]);
            exit();
        }

        $this->connection->set_charset($this->charset);
    }

    public function getConnection(){
        return $this->connection;
    }
}
?>