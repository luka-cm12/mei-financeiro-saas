# MEI Financeiro - API REST

API REST em PHP para o sistema de gestão financeira de MEIs e autônomos.

## Configuração do Ambiente

### 1. XAMPP
- Instale o XAMPP
- Inicie Apache e MySQL
- Acesse `http://localhost/phpmyadmin`
- Crie o banco de dados `mei_financeiro`

### 2. Banco de Dados
Execute o script SQL em `database/schema.sql` para criar as tabelas.

### 3. Configuração
Edite `config/Database.php` com suas credenciais do banco.

## Endpoints da API

### Autenticação
- `POST /api/auth/register` - Registro de usuário
- `POST /api/auth/login` - Login
- `POST /api/auth/refresh` - Refresh token

### Usuários
- `GET /api/user/profile` - Perfil do usuário
- `PUT /api/user/profile` - Atualizar perfil

### Transações
- `GET /api/transactions` - Listar transações
- `POST /api/transactions` - Criar transação
- `PUT /api/transactions/{id}` - Atualizar transação
- `DELETE /api/transactions/{id}` - Deletar transação

### Categorias
- `GET /api/categories` - Listar categorias
- `POST /api/categories` - Criar categoria

### Relatórios
- `GET /api/reports/monthly/{year}/{month}` - Relatório mensal
- `GET /api/reports/profit/{year}/{month}` - Cálculo de lucro
- `GET /api/reports/charts/{year}/{month}` - Dados para gráficos

### Assinatura
- `GET /api/subscription/status` - Status da assinatura
- `POST /api/subscription/webhook` - Webhook do pagamento

## Estrutura do Projeto

```
api/
├── config/           # Configurações
├── controllers/      # Controladores da API
├── models/          # Modelos de dados
├── middleware/      # Middleware (autenticação, CORS)
├── database/        # Scripts SQL
└── index.php        # Ponto de entrada
```

## Instalação

1. Clone o projeto no diretório `htdocs` do XAMPP
2. Configure o banco de dados
3. Execute o schema SQL
4. Teste a API em `http://localhost/mei-financeiro-saas/api`