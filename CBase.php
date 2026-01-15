<?php
class CBase {
    private $db_host = "bd6nwozkfuy3bx0r6czs-mysql.services.clever-cloud.com";
    private $db_user = "uvrlnlkgonttshoc";
    private $db_password = "BmR9D1le4UryBaTkij03";
    private $db_name = "bd6nwozkfuy3bx0r6czs";
    private $charset = "utf8";
    private $connection;

    public function __construct(){
        $this->connection = new mysqli(
            $this->db_host,
            $this->db_user,
            $this->db_password,
            $this->db_name
        );

        if($this->connection->connect_errno){
            echo json_encode([
                "status" => "error",
                "message" => "Error de conexión: " . $this->connection->connect_error
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