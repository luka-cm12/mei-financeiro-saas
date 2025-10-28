<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ProductController {
    private $db;
    private $product;
    
    public function __construct($database) {
        $this->db = $database;
        $this->product = new Product($this->db);
    }
    
    /**
     * Lista produtos do estabelecimento
     */
    public function getProducts() {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            $search = $_GET['search'] ?? '';
            
            if (!empty($search)) {
                $products = $this->product->searchProducts($establishment_data['id'], $search);
            } else {
                $products = $this->product->getProducts($establishment_data['id']);
            }
            
            return $this->sendResponse(200, true, 'Produtos listados com sucesso', $products);
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Busca produto específico
     */
    public function getProduct($product_id) {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            $product = $this->product->getProduct($product_id, $establishment_data['id']);
            
            if (!$product) {
                return $this->sendResponse(404, false, 'Produto não encontrado');
            }
            
            return $this->sendResponse(200, true, 'Produto encontrado', $product);
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Cria novo produto
     */
    public function createProduct() {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Adicionar establishment_id aos dados
            $input['establishment_id'] = $establishment_data['id'];
            
            // Validar dados
            $validation_errors = $this->product->validateProductData($input);
            if (!empty($validation_errors)) {
                return $this->sendResponse(400, false, 'Dados inválidos', $validation_errors);
            }
            
            $product_id = $this->product->create($input);
            
            if ($product_id) {
                $new_product = $this->product->getProduct($product_id, $establishment_data['id']);
                return $this->sendResponse(201, true, 'Produto criado com sucesso', $new_product);
            } else {
                return $this->sendResponse(500, false, 'Erro ao criar produto');
            }
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualiza produto
     */
    public function updateProduct($product_id) {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            // Verificar se produto existe
            $existing_product = $this->product->getProduct($product_id, $establishment_data['id']);
            if (!$existing_product) {
                return $this->sendResponse(404, false, 'Produto não encontrado');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Adicionar establishment_id aos dados
            $input['establishment_id'] = $establishment_data['id'];
            
            // Validar dados
            $validation_errors = $this->product->validateProductData($input);
            if (!empty($validation_errors)) {
                return $this->sendResponse(400, false, 'Dados inválidos', $validation_errors);
            }
            
            if ($this->product->update($product_id, $input)) {
                $updated_product = $this->product->getProduct($product_id, $establishment_data['id']);
                return $this->sendResponse(200, true, 'Produto atualizado com sucesso', $updated_product);
            } else {
                return $this->sendResponse(500, false, 'Erro ao atualizar produto');
            }
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Remove produto
     */
    public function deleteProduct($product_id) {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            // Verificar se produto existe
            $existing_product = $this->product->getProduct($product_id, $establishment_data['id']);
            if (!$existing_product) {
                return $this->sendResponse(404, false, 'Produto não encontrado');
            }
            
            if ($this->product->delete($product_id, $establishment_data['id'])) {
                return $this->sendResponse(200, true, 'Produto removido com sucesso');
            } else {
                return $this->sendResponse(500, false, 'Erro ao remover produto');
            }
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Lista produtos mais vendidos
     */
    public function getMostSoldProducts() {
        try {
            $user_id = $this->getUserIdFromToken();
            if (!$user_id) {
                return $this->sendResponse(401, false, 'Token inválido');
            }
            
            // Buscar establishment_id do usuário
            $establishment = new Establishment($this->db);
            $establishment_data = $establishment->getByUserId($user_id);
            
            if (!$establishment_data) {
                return $this->sendResponse(404, false, 'Estabelecimento não encontrado');
            }
            
            $limit = $_GET['limit'] ?? 10;
            $products = $this->product->getMostSoldProducts($establishment_data['id'], $limit);
            
            return $this->sendResponse(200, true, 'Produtos mais vendidos listados com sucesso', $products);
            
        } catch (Exception $e) {
            return $this->sendResponse(500, false, 'Erro interno: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtém ID do usuário do token JWT
     */
    private function getUserIdFromToken() {
        $headers = apache_request_headers();
        $token = $headers['Authorization'] ?? '';
        
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            return $decoded->user_id;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Envia resposta JSON
     */
    private function sendResponse($status_code, $success, $message, $data = null) {
        http_response_code($status_code);
        header('Content-Type: application/json');
        
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        return true;
    }
}
?>