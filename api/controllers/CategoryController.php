<?php
/**
 * Controlador de categorias
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/Category.php';

class CategoryController {
    private $db;
    private $auth;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->auth = new AuthMiddleware();
    }
    
    public function getCategories() {
        $auth_data = $this->auth->authenticate();
        
        try {
            $category = new Category($this->db);
            $categories = $category->getByUser($auth_data->user_id);
            
            echo json_encode([
                "categories" => $categories
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
    
    public function createCategory() {
        $auth_data = $this->auth->authenticate();
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validações
        if (!isset($data['name']) || !isset($data['type'])) {
            http_response_code(400);
            echo json_encode(["message" => "Nome e tipo são obrigatórios"]);
            return;
        }
        
        if (!in_array($data['type'], ['receita', 'despesa'])) {
            http_response_code(400);
            echo json_encode(["message" => "Tipo deve ser 'receita' ou 'despesa'"]);
            return;
        }
        
        try {
            $category = new Category($this->db);
            $data['user_id'] = $auth_data->user_id;
            
            $category_id = $category->create($data);
            
            http_response_code(201);
            echo json_encode([
                "message" => "Categoria criada com sucesso",
                "category_id" => $category_id
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Erro interno: " . $e->getMessage()]);
        }
    }
}
?>