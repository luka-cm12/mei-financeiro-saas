# 🧪 Testando a API com Postman - MEI Financeiro SaaS

## 📥 Instalação do Postman

1. **Baixe o Postman**: https://www.postman.com/downloads/
2. **Instale** e crie uma conta (ou use sem conta)
3. **Abra** o Postman

## 🔧 Configuração Inicial

### 1. Criar uma Nova Collection
1. Clique em **"New Collection"**
2. Nome: **"MEI Financeiro API"**
3. Descrição: **"Testes da API do sistema MEI Financeiro"**

### 2. Configurar Variáveis de Ambiente
1. Clique no ⚙️ **Settings** (canto superior direito)
2. Vá em **"Environments"** 
3. Clique **"Add"**
4. Nome: **"MEI Local"**
5. Adicione as variáveis:

| Variable | Initial Value | Current Value |
|----------|---------------|---------------|
| `base_url` | `http://localhost/mei-financeiro-saas/api` | `http://localhost/mei-financeiro-saas/api` |
| `token` | *(deixe vazio)* | *(deixe vazio)* |

## 🚀 Testando os Endpoints

### 1. Teste Básico - Status da API

**Método**: `GET`  
**URL**: `{{base_url}}/`  
**Headers**: *(nenhum)*

**Resposta esperada**:
```json
{
  "message": "MEI Financeiro API v1.0",
  "status": "running"
}
```

### 2. Registro de Usuário

**Método**: `POST`  
**URL**: `{{base_url}}/auth/register`  
**Headers**: 
```
Content-Type: application/json
```

**Body** (raw JSON):
```json
{
  "name": "João Silva",
  "email": "joao@email.com", 
  "password": "123456",
  "business_name": "João Delivery"
}
```

**Resposta esperada**:
```json
{
  "message": "Usuário criado com sucesso",
  "user_id": 1,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### 3. Login de Usuário

**Método**: `POST`  
**URL**: `{{base_url}}/auth/login`  
**Headers**: 
```
Content-Type: application/json
```

**Body** (raw JSON):
```json
{
  "email": "joao@email.com",
  "password": "123456"
}
```

**Resposta esperada**:
```json
{
  "message": "Login realizado com sucesso",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@email.com",
    "business_name": "João Delivery"
  }
}
```

**📝 IMPORTANTE**: Copie o `token` da resposta e cole na variável de ambiente `token`

### 4. Listar Categorias (Autenticado)

**Método**: `GET`  
**URL**: `{{base_url}}/categories`  
**Headers**: 
```
Authorization: Bearer {{token}}
Content-Type: application/json
```

**Resposta esperada**:
```json
[
  {
    "id": 1,
    "name": "Vendas",
    "type": "income",
    "color": "#4CAF50"
  },
  {
    "id": 2, 
    "name": "Alimentação",
    "type": "expense",
    "color": "#F44336"
  }
]
```

### 5. Criar Transação (Receita)

**Método**: `POST`  
**URL**: `{{base_url}}/transactions`  
**Headers**: 
```
Authorization: Bearer {{token}}
Content-Type: application/json
```

**Body** (raw JSON):
```json
{
  "type": "income",
  "amount": 150.00,
  "description": "Venda de produto X",
  "category_id": 1,
  "date": "2024-10-26"
}
```

### 6. Criar Transação (Despesa)

**Método**: `POST`  
**URL**: `{{base_url}}/transactions`  
**Headers**: 
```
Authorization: Bearer {{token}}
Content-Type: application/json
```

**Body** (raw JSON):
```json
{
  "type": "expense", 
  "amount": 45.50,
  "description": "Almoço",
  "category_id": 2,
  "date": "2024-10-26"
}
```

### 7. Listar Transações

**Método**: `GET`  
**URL**: `{{base_url}}/transactions`  
**Headers**: 
```
Authorization: Bearer {{token}}
Content-Type: application/json
```

**Query Params** (opcionais):
- `page=1`
- `limit=10` 
- `type=income` ou `expense`
- `month=2024-10`

### 8. Status da Assinatura

**Método**: `GET`  
**URL**: `{{base_url}}/subscription/status`  
**Headers**: 
```
Authorization: Bearer {{token}}
Content-Type: application/json
```

### 9. Criar Assinatura

**Método**: `POST`  
**URL**: `{{base_url}}/subscription/create`  
**Headers**: 
```
Authorization: Bearer {{token}}
Content-Type: application/json
```

**Body** (raw JSON):
```json
{
  "plan": "monthly"
}
```

## 📋 Fluxo Completo de Teste

### Passo a Passo Recomendado:

1. **✅ Teste Status** - Verifique se API está rodando
2. **✅ Registre Usuário** - Crie uma conta de teste
3. **✅ Faça Login** - Obtenha o token de autenticação
4. **✅ Liste Categorias** - Veja as categorias padrão
5. **✅ Crie Receitas** - Adicione algumas vendas
6. **✅ Crie Despesas** - Adicione alguns gastos
7. **✅ Liste Transações** - Veja o histórico
8. **✅ Teste Assinatura** - Simule o sistema de cobrança

## 🔑 Dicas Importantes

### Autenticação
- Sempre inclua o header `Authorization: Bearer {{token}}`
- O token expira, faça login novamente se necessário
- Use variáveis de ambiente para facilitar os testes

### Tratamento de Erros
**Status 401 - Unauthorized**:
```json
{
  "error": "Token inválido ou expirado"
}
```

**Status 422 - Validation Error**:
```json
{
  "error": "Dados inválidos",
  "details": {
    "email": "Email é obrigatório",
    "password": "Senha deve ter pelo menos 6 caracteres"
  }
}
```

**Status 404 - Not Found**:
```json
{
  "error": "Recurso não encontrado"
}
```

### Headers Importantes
```
Content-Type: application/json
Authorization: Bearer {seu_token_jwt}
Accept: application/json
```

## 🎯 Testes Avançados

### Teste de Paginação
```
GET {{base_url}}/transactions?page=1&limit=5
```

### Teste de Filtros
```
GET {{base_url}}/transactions?type=income&month=2024-10
```

### Teste de Validação
Tente criar transação com dados inválidos:
```json
{
  "type": "invalid",
  "amount": -100,
  "description": ""
}
```

## 🚨 Solução de Problemas

### Erro: "Connection refused"
- ✅ Verifique se XAMPP está rodando
- ✅ Confirme a URL: `http://localhost/mei-financeiro-saas/api`

### Erro: "Database connection failed"
- ✅ Inicie MySQL no XAMPP
- ✅ Verifique `api/config/database.php`
- ✅ Confirme se o banco foi criado

### Erro: "Token inválido"
- ✅ Faça login novamente
- ✅ Copie o token completo
- ✅ Inclua "Bearer " antes do token

---

## 📚 Collection Completa

Você pode importar esta collection no Postman:

1. **File** → **Import**
2. **Raw Text** e cole o JSON da collection
3. Configure as variáveis de ambiente
4. Execute os testes em sequência

**🎉 Pronto!** Agora você pode testar toda a API do MEI Financeiro SaaS no Postman!