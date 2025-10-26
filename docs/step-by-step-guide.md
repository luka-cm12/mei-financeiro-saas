# 🚀 PASSO A PASSO COMPLETO - MEI Financeiro SaaS

## 📋 ANTES DE COMEÇAR

### ✅ Verificar se você tem:
- [ ] Windows 10/11
- [ ] Conexão com internet
- [ ] Pelo menos 2GB de espaço livre
- [ ] Permissões de administrador

---

## 🔧 ETAPA 1: INSTALANDO O XAMPP

### 1.1. Download do XAMPP
1. **Abra seu navegador**
2. **Vá para**: https://www.apachefriends.org/
3. **Clique em "Download"**
4. **Escolha**: "XAMPP for Windows" (versão 8.2.12 ou superior)
5. **Aguarde o download** (aproximadamente 150MB)

### 1.2. Instalação do XAMPP
1. **Localize o arquivo baixado**: `xampp-windows-x64-8.2.12-0-VS16-installer.exe`
2. **Clique com botão direito** → **"Executar como administrador"**
3. **Clique "Next"** em todas as telas
4. **Escolha a pasta**: `C:\xampp` (padrão)
5. **Aguarde a instalação** (5-10 minutos)
6. **Marque "Start Control Panel"** no final
7. **Clique "Finish"**

### 1.3. Configurar XAMPP
1. **Painel de Controle do XAMPP abrirá**
2. **Clique "Start"** ao lado de **Apache** ✅
3. **Clique "Start"** ao lado de **MySQL** ✅
4. **Aguarde** até ficarem verdes (Apache e MySQL)

> ⚠️ **Se der erro de porta**: Clique "Config" → "Apache" → "httpd.conf" → Mude porta 80 para 8080

---

## 💾 ETAPA 2: CONFIGURANDO O BANCO DE DADOS

### 2.1. Acessar phpMyAdmin
1. **Abra seu navegador**
2. **Digite na barra de endereço**: `http://localhost/phpmyadmin`
3. **Pressione Enter**
4. **Aguarde carregar** a interface do phpMyAdmin

### 2.2. Criar o Banco de Dados
1. **Clique em "Databases"** (no topo)
2. **Digite o nome**: `mei_financeiro_db`
3. **Escolha "Collation"**: `utf8mb4_general_ci`
4. **Clique "Create"**
5. **Confirme** que apareceu na lista à esquerda

### 2.3. Executar o Schema SQL
1. **Clique no banco** `mei_financeiro_db` (à esquerda)
2. **Clique na aba "SQL"** (no topo)
3. **Abra o arquivo**: `C:\xampp\htdocs\mei-financeiro-saas\api\database\schema.sql`
4. **Copie todo o conteúdo** (Ctrl+A, Ctrl+C)
5. **Cole na caixa SQL** do phpMyAdmin
6. **Clique "Go"** (botão azul)
7. **Verifique** se criou as tabelas (users, transactions, etc.)

---

## 📱 ETAPA 3: INSTALANDO O FLUTTER

### 3.1. Download do Flutter SDK
1. **Vá para**: https://flutter.dev/docs/get-started/install/windows
2. **Clique "Download Flutter SDK"**
3. **Baixe o arquivo ZIP** (aproximadamente 1.5GB)
4. **Aguarde o download completo**

### 3.2. Extrair Flutter
1. **Crie a pasta**: `C:\flutter`
2. **Extraia o ZIP baixado** para `C:\flutter\`
3. **Resultado**: `C:\flutter\flutter\bin\flutter.exe`

### 3.3. Configurar PATH do Flutter
1. **Pressione**: `Windows + R`
2. **Digite**: `sysdm.cpl`
3. **Pressione Enter**
4. **Clique "Environment Variables"**
5. **Na seção "System Variables"**, encontre **"Path"**
6. **Clique "Edit"**
7. **Clique "New"**
8. **Adicione**: `C:\flutter\flutter\bin`
9. **Clique "OK"** em todas as janelas

### 3.4. Verificar Instalação
1. **Abra PowerShell** (Windows + X → PowerShell)
2. **Digite**: `flutter doctor`
3. **Pressione Enter**
4. **Aguarde** a verificação completa
5. **Anote** os problemas mostrados (normal ter alguns ⚠️)

---

## 🔧 ETAPA 4: CONFIGURANDO O PROJETO

### 4.1. Verificar Estrutura de Arquivos
1. **Abra o Explorador de Arquivos**
2. **Navegue para**: `C:\xampp\htdocs\mei-financeiro-saas`
3. **Verifique se existe**:
   - ✅ Pasta `api/`
   - ✅ Pasta `flutter_app/`
   - ✅ Pasta `docs/`
   - ✅ Arquivo `README.md`

### 4.2. Configurar Banco de Dados na API
1. **Abra**: `C:\xampp\htdocs\mei-financeiro-saas\api\config\database.php`
2. **Verifique as configurações**:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mei_financeiro_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // Vazio no XAMPP
```
3. **Salve o arquivo** (Ctrl+S)

### 4.3. Testar API
1. **Abra seu navegador**
2. **Digite**: `http://localhost/mei-financeiro-saas/api/`
3. **Pressione Enter**
4. **Deve mostrar**: 
```json
{
  "message": "MEI Financeiro API v1.0",
  "status": "running"
}
```

> ❌ **Se não funcionar**: Verifique se Apache está verde no XAMPP

---

## 📱 ETAPA 5: CONFIGURANDO O FLUTTER

### 5.1. Instalar Dependências
1. **Abra PowerShell**
2. **Navegue para o projeto**:
```powershell
cd C:\xampp\htdocs\mei-financeiro-saas\flutter_app
```
3. **Instale as dependências**:
```powershell
flutter pub get
```
4. **Aguarde** o download (2-5 minutos)

### 5.2. Verificar Problemas
1. **Execute**:
```powershell
flutter analyze
```
2. **Aguarde** a análise
3. **Pode ter warnings** (normal, não impedem funcionamento)

### 5.3. Configurar Emulador Android (Opcional)
1. **Baixe Android Studio**: https://developer.android.com/studio
2. **Instale** seguindo assistente
3. **Abra Android Studio**
4. **Tools** → **AVD Manager**
5. **Create Virtual Device**
6. **Escolha um dispositivo** (ex: Pixel 7)
7. **Download da API** (API 34 recomendada)
8. **Create AVD**

---

## 🧪 ETAPA 6: TESTANDO COM POSTMAN

### 6.1. Instalar Postman
1. **Vá para**: https://www.postman.com/downloads/
2. **Clique "Download"**
3. **Execute o instalador**
4. **Abra o Postman**
5. **Pode pular** criação de conta (Not now)

### 6.2. Importar Collection
1. **No Postman, clique "Import"**
2. **Clique "Upload Files"**
3. **Selecione**: `C:\xampp\htdocs\mei-financeiro-saas\docs\MEI-Financeiro-API.postman_collection.json`
4. **Clique "Import"**
5. **Confirme** que apareceu "MEI Financeiro SaaS API" na sidebar

### 6.3. Executar Testes
1. **Clique na collection** "MEI Financeiro SaaS API"
2. **Execute na ordem**:
   - ✅ **1. Status da API** → Deve retornar status 200
   - ✅ **2. Registrar Usuário** → Cria conta e salva token
   - ✅ **3. Login** → Testa autenticação  
   - ✅ **4. Listar Categorias** → Mostra categorias padrão
   - ✅ **5. Criar Receita** → Adiciona venda de R$ 150
   - ✅ **6. Criar Despesa** → Adiciona gasto de R$ 45,50
   - ✅ **7. Listar Transações** → Vê histórico
   - ✅ **8. Filtrar por Receitas** → Filtra apenas entradas
   - ✅ **9. Status Assinatura** → Verifica plano
   - ✅ **10. Criar Assinatura** → Simula R$ 19,90/mês

---

## 📱 ETAPA 7: EXECUTANDO O APP FLUTTER

### 7.1. Executar no Chrome (Mais Fácil)
1. **No PowerShell, dentro da pasta flutter_app**:
```powershell
flutter run -d chrome
```
2. **Aguarde** compilação (3-5 minutos na primeira vez)
3. **Abrirá** o app no navegador
4. **Teste** o fluxo completo:
   - Registrar usuário
   - Fazer login
   - Adicionar transações
   - Ver dashboard

### 7.2. Executar no Android (Se configurou emulador)
1. **Inicie o emulador** no Android Studio
2. **No PowerShell**:
```powershell
flutter devices
```
3. **Confirme** que mostra o emulador
4. **Execute**:
```powershell
flutter run
```
5. **Aguarde** instalação no emulador

---

## 🔍 ETAPA 8: VERIFICAÇÃO FINAL

### 8.1. Checklist de Funcionamento
- [ ] **XAMPP rodando**: Apache e MySQL verdes
- [ ] **Banco criado**: `mei_financeiro_db` com tabelas
- [ ] **API funcionando**: Responde em `localhost/mei-financeiro-saas/api`
- [ ] **Postman testado**: Todos endpoints funcionando
- [ ] **Flutter compilando**: Sem erros críticos
- [ ] **App abrindo**: Interface carrega corretamente

### 8.2. Teste do Fluxo Completo
1. **Abra o app Flutter**
2. **Clique "Registrar"**
3. **Preencha dados**:
   - Nome: Seu nome
   - Email: seuemail@teste.com
   - Senha: 123456
   - Negócio: Sua empresa teste
4. **Clique "Criar Conta"**
5. **Faça login** com os mesmos dados
6. **No Dashboard**:
   - Clique "+" para adicionar transação
   - Adicione uma receita de R$ 100
   - Adicione uma despesa de R$ 30
   - Veja o lucro calculado (R$ 70)

---

## 🚨 SOLUÇÃO DE PROBLEMAS COMUNS

### ❌ API não responde
**Sintoma**: Erro 404 ou página não carrega
**Solução**:
1. Verifique se Apache está verde no XAMPP
2. Confirme URL: `http://localhost/mei-financeiro-saas/api/`
3. Reinicie Apache no XAMPP

### ❌ Erro de banco de dados
**Sintoma**: "Database connection failed"
**Solução**:
1. Verifique se MySQL está verde no XAMPP
2. Confirme se banco `mei_financeiro_db` existe
3. Verifique credenciais em `api/config/database.php`

### ❌ Flutter não compila
**Sintoma**: Erros ao executar `flutter run`
**Solução**:
```powershell
flutter clean
flutter pub get
flutter doctor
```

### ❌ Postman erro 401
**Sintoma**: "Token inválido"
**Solução**:
1. Execute novamente "3. Login"
2. Copie o token manualmente se necessário
3. Verifique header: `Authorization: Bearer {token}`

### ❌ Porta em uso (XAMPP)
**Sintoma**: Apache não inicia - porta 80 ocupada
**Solução**:
1. No XAMPP, clique "Config" → "Apache" 
2. Edite `httpd.conf`
3. Mude `Listen 80` para `Listen 8080`
4. Mude `ServerName localhost:80` para `localhost:8080`
5. Reinicie Apache
6. Use URLs como: `http://localhost:8080/mei-financeiro-saas/api/`

---

## 🎉 PARABÉNS!

Se chegou até aqui, seu **MEI Financeiro SaaS está funcionando 100%**! 

### 📊 O que você conseguiu:
- ✅ **Backend PHP** rodando com JWT
- ✅ **Banco MySQL** estruturado
- ✅ **API REST** com todos endpoints
- ✅ **App Flutter** multiplataforma
- ✅ **Sistema de assinatura** R$ 19,90/mês
- ✅ **Dashboard financeiro** completo

### 🚀 Próximos passos:
1. **Personalizar** categorias e cores
2. **Adicionar** mais transações de teste
3. **Integrar** gateway de pagamento real
4. **Deploy** para produção
5. **Publicar** nas lojas de apps

### 📞 Precisa de ajuda?
- Consulte: `docs/postman-testing-guide.md`
- Verifique: `docs/installation-guide.md`
- Analise logs do PHP no XAMPP

**🎯 Sistema pronto para uso e expansão!**