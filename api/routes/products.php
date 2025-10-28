<?php
// Produtos - CRUD completo
$router->get('/api/products', function() use ($database) {
    require_once __DIR__ . '/../models/Product.php';
    require_once __DIR__ . '/../controllers/ProductController.php';
    
    $controller = new ProductController($database);
    return $controller->getProducts();
});

$router->get('/api/products/{id}', function($id) use ($database) {
    require_once __DIR__ . '/../models/Product.php';
    require_once __DIR__ . '/../controllers/ProductController.php';
    
    $controller = new ProductController($database);
    return $controller->getProduct($id);
});

$router->post('/api/products', function() use ($database) {
    require_once __DIR__ . '/../models/Product.php';
    require_once __DIR__ . '/../controllers/ProductController.php';
    
    $controller = new ProductController($database);
    return $controller->createProduct();
});

$router->put('/api/products/{id}', function($id) use ($database) {
    require_once __DIR__ . '/../models/Product.php';
    require_once __DIR__ . '/../controllers/ProductController.php';
    
    $controller = new ProductController($database);
    return $controller->updateProduct($id);
});

$router->delete('/api/products/{id}', function($id) use ($database) {
    require_once __DIR__ . '/../models/Product.php';
    require_once __DIR__ . '/../controllers/ProductController.php';
    
    $controller = new ProductController($database);
    return $controller->deleteProduct($id);
});

$router->get('/api/products/analytics/most-sold', function() use ($database) {
    require_once __DIR__ . '/../models/Product.php';
    require_once __DIR__ . '/../controllers/ProductController.php';
    
    $controller = new ProductController($database);
    return $controller->getMostSoldProducts();
});
?>