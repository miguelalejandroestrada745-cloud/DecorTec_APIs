<?php
class CBase {
    private $db_host = "sql101.infinityfree.com";
    private $db_user = "if0_40908733";
    private $db_password = "Estrella070400";
    private $db_name = "if0_40908733_decortec";
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