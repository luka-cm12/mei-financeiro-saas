# 📋 Collection Postman - MEI Financeiro SaaS API

## 🚀 Como Usar

### 1. **Importar no Postman**
- Abra o Postman
- Clique em **Import**
- Selecione o arquivo `MEI-Financeiro-API.postman_collection.json`
- A collection será importada automaticamente

### 2. **Configurar Variáveis**
- Crie um **Environment** no Postman
- Adicione as variáveis:
  - `base_url`: `http://localhost/mei-financeiro-saas/api`
  - `token`: (será preenchido automaticamente após login)
  - `test_email`: (será preenchido automaticamente no registro)

### 3. **⚠️ IMPORTANTE - Ordem de Execução**
1. **Execute "Registrar Usuário" primeiro** - isso gerará um email único
2. **Execute "Login" em seguida** - usará o mesmo email gerado
3. **Todos os outros endpoints** - usarão o token do login automaticamente

## 📌 endpoints Disponíveis

### ✅ **Funcionando Perfeitamente**

#### 🔐 **Autenticação**
1. **Status da API** - `GET {{base_url}}/`
2. **Registrar Usuário** - `POST {{base_url}}/auth/register.php`
3. **Login** - `POST {{base_url}}/auth/login.php`

#### 🧾 **NFCe (Nota Fiscal)**
4. **NFCe - Autorizar** - `POST {{base_url}}/routes/nfce.php`
5. **NFCe - Cancelar** - `DELETE {{base_url}}/routes/nfce.php`

#### 🏢 **Estabelecimento**
6. **Configurar Estabelecimento** - `POST {{base_url}}/establishment.php`

#### 📦 **Produtos**
7. **Listar Produtos** - `GET {{base_url}}/routes/products.php`

#### 📊 **Analytics**
8. **Dashboard Analytics** - `GET {{base_url}}/analytics.php?action=dashboard`

## 🔧 **Campos Obrigatórios**

### 📝 **Registro**
```json
{
  "name": "João Silva",
  "email": "joao@email.com", 
  "password": "123456",
  "confirm_password": "123456",
  "phone": "11999999999",
  "document": "11144477735"
}
```

### 🔑 **Login**
```json
{
  "email": "joao@email.com",
  "password": "123456"
}
```

### 🧾 **NFCe**
```json
{
  "items": [
    {
      "description": "Produto Teste",
      "quantity": 1,
      "unit_price": 25.00,
      "total": 25.00,
      "cfop": "5102",
      "ncm": "12345678"
    }
  ],
  "payment": {
    "type": "money",
    "amount": 25.00
  }
}
```

## ⚡ **Features Implementadas**

✅ **Autenticação JWT**
✅ **Validação de CPF**
✅ **Sistema de Trial (7 dias grátis)**
✅ **Integração SEFAZ (webservices oficiais)**
✅ **Geração DANFE PDF com QR Code**
✅ **Contingência offline para NFCe**
✅ **Envio automático por email**
✅ **Relatórios fiscais (SPED, DTE)**
✅ **Analytics e métricas**
✅ **Gerenciamento de produtos**

## 🎯 **Exemplo de Uso no Postman**

1. **Teste Status**: `GET {{base_url}}/`
2. **Registre um usuário**: Use o endpoint de registro
3. **Faça login**: O token será salvo automaticamente
4. **Teste NFCe**: Use o token para autorizar uma nota fiscal
5. **Explore Analytics**: Veja os dados do dashboard

## 📞 **Suporte**

- Sistema 100% funcional
- Responses padronizados em JSON
- Tokens JWT automáticos
- Validações completas

## 🔧 **Troubleshooting**

### PowerShell JSON Error
Se testar via PowerShell e receber "JSON mal formatado":
- ✅ **Solução**: Use arquivos JSON ao invés de strings inline
- ✅ **Exemplo**: `Invoke-RestMethod -InFile "arquivo.json"` 
- ✅ **No Postman**: Não há problema, funciona perfeitamente

### Email já existe
Se receber "email já está cadastrado":
- ✅ **No Postman**: O script gera emails únicos automaticamente
- ✅ **Manual**: Altere o email no body da request

🚀 **Pronto para produção!**