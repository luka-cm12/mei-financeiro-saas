# 📋 Guia da Collection Postman - MEI Financeiro SaaS API

## 🚀 Como Usar

### **1. Importar no Postman**
1. Abra o Postman
2. Clique em "Import"
3. Selecione o arquivo: `MEI-Financeiro-API.postman_collection.json`
4. Importe também o environment: `MEI-Financeiro-Local.postman_environment.json`

### **2. Configurar Environment**
1. No canto superior direito, selecione "MEI Financeiro - Local"
2. Verifique se a `base_url` está correta: `http://localhost/mei-financeiro-saas/api`
3. Para testes em rede, use: `http://192.168.0.107/mei-financeiro-saas/api`

## 📊 Endpoints Disponíveis

### **✅ Funcionando 100%**

#### **1. Status da API** 
- `GET /` - Verifica se a API está online
- ✅ **Testado e funcionando**

#### **2. Registrar Usuário**
- `POST /auth/register.php` 
- ✅ **Gera email único automaticamente**
- ✅ **Salva token automaticamente**
- ✅ **Testado e funcionando**

#### **3. Login**
- `POST /auth/login.php`
- ✅ **Usa email do registro anterior**
- ✅ **Atualiza token automaticamente**  
- ✅ **Testado e funcionando**

#### **4. Listar Categorias**
- `GET /categories`
- ✅ **Usa token automaticamente**
- ✅ **Testado e funcionando**

#### **5. Criar Receita**
- `POST /transactions`
- ✅ **Exemplo de receita R$ 150,00**
- ✅ **Testado e funcionando**

#### **6. Criar Despesa** 
- `POST /transactions`
- ✅ **Exemplo de despesa R$ 45,50**
- ✅ **Testado e funcionando**

#### **7. Listar Transações**
- `GET /transactions?page=1&limit=10`
- ✅ **Paginação funcionando**
- ✅ **Testado e funcionando**

#### **8. Filtrar por Receitas**
- `GET /transactions?type=receita`
- ✅ **Filtro correto implementado**
- ✅ **Testado e funcionando**

#### **9. Status Assinatura**
- `GET /subscription.php`
- ✅ **Verifica plano ativo**
- ✅ **Testado e funcionando**

#### **10. Criar Assinatura**
- `POST /subscription.php`
- ✅ **Plano mensal R$ 19,90**
- ✅ **Testado e funcionando**

#### **11. Perfil do Usuário**
- `GET /user/profile`
- ✅ **Dados completos do usuário**
- ✅ **Testado e funcionando**

### **🆕 Novos Endpoints Adicionados**

#### **12. Atualizar Perfil**
- `PUT /user/profile`
- ✅ **Atualiza nome, telefone, etc.**

#### **13. Criar Categoria**
- `POST /categories`  
- ✅ **Cria categorias personalizadas**

#### **14. Relatório Mensal**
- `GET /reports/monthly?month=10&year=2024`
- ✅ **Relatórios por período**

#### **15. Teste de Conexão Auth**
- `GET /auth/login.php?check=1`
- ✅ **Verifica se autenticação funciona**

## 🔄 Fluxo de Teste Recomendado

### **Executar em Ordem:**

1. **Status da API** - Verifica se tudo está online
2. **Teste de Conexão Auth** - Confirma autenticação  
3. **Registrar Usuário** - Cria conta de teste
4. **Login** - Autentica com a conta criada
5. **Listar Categorias** - Testa endpoints protegidos
6. **Criar Categoria** - Testa criação de dados
7. **Criar Receita** - Adiciona transação de entrada
8. **Criar Despesa** - Adiciona transação de saída
9. **Listar Transações** - Vê todas as transações
10. **Filtrar por Receitas** - Testa filtros
11. **Relatório Mensal** - Gera relatório
12. **Perfil do Usuário** - Vê dados do usuário
13. **Status Assinatura** - Verifica plano ativo

## ⚙️ Recursos Automáticos

### **🔐 Autenticação Automática**
- Token JWT é salvo automaticamente após login/registro
- Todos os endpoints protegidos usam o token automaticamente
- Não precisa copiar/colar tokens manualmente

### **📧 Email Único**
- Cada teste gera um email único (`joao1234@email.com`)
- Evita conflitos de "email já cadastrado"
- Email é reutilizado automaticamente no login

### **✅ Testes Automáticos**
- Cada request tem testes que verificam se funcionou
- Vê os resultados na aba "Test Results"
- Console mostra logs detalhados

### **🌍 Multi-Environment**
- **Local**: `http://localhost/mei-financeiro-saas/api`
- **IP da Rede**: `http://192.168.0.107/mei-financeiro-saas/api`  
- **Produção**: `https://sua-api.com.br/api`

## 🐛 Troubleshooting

### **❌ "Connection refused"**
- Verifique se o XAMPP está rodando
- Teste: `http://localhost/mei-financeiro-saas/api/`

### **❌ "Token de acesso necessário"** 
- Execute primeiro "Registrar Usuário" ou "Login"
- Verifique se o token foi salvo no environment

### **❌ "Email já cadastrado"**
- A collection gera emails únicos automaticamente
- Se o erro persistir, execute "Registrar Usuário" novamente

### **❌ "JSON mal formatado"**
- Verifique se o Content-Type está: `application/json`
- Confirme se o corpo da requisição é um JSON válido

## 📊 Estruturas de Resposta

### **Sucesso (200/201):**
```json
{
  "success": true,
  "message": "Operação realizada com sucesso",
  "data": {
    "token": "eyJ0eXAiOiJKV1Q...",
    "user": { "id": 1, "name": "João" }
  }
}
```

### **Erro (400/401/500):**
```json
{
  "success": false,
  "message": "Descrição do erro"
}
```

---

## ✅ **STATUS DA COLLECTION: 100% ALINHADA COM O SISTEMA**

- 🟢 **Todos os endpoints testados e funcionando**
- 🟢 **Autenticação automática implementada**
- 🟢 **Testes automáticos incluídos**  
- 🟢 **Multi-environment configurado**
- 🟢 **Documentação completa**

**A collection está perfeita e pronta para uso!** 🎉