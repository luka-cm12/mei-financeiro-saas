<?php
/**
 * Configuração da base de dados
 */
class Database {
    private $host = "localhost";
    private $db_name = "mei_financeiro";
    private $username = "root";
    private $password = "";
    private $conn = null;

    public function getConnection() {
        if ($this->conn === null) {
            try {
                $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch(PDOException $exception) {
                throw new Exception("Erro de conexão: " . $exception->getMessage());
            }
        }
        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}
?>