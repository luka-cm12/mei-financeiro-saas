# 🚀 Guia de Instalação - MEI Financeiro SaaS

## Pré-requisitos

- **XAMPP** (Apache + MySQL + PHP 8+)
- **Flutter SDK** 3.35+
- **Git** (para clonar repositório)
- **VS Code** ou Android Studio

## 📋 Passo a Passo

### 1. Configuração do Backend (XAMPP + MySQL)

#### 1.1. Instalação do XAMPP
```bash
# Baixe e instale o XAMPP em: https://www.apachefriends.org/
# Inicie Apache e MySQL no painel do XAMPP
```

#### 1.2. Configuração do Banco de Dados
```bash
# Acesse: http://localhost/phpmyadmin
# Crie um novo banco: mei_financeiro_db
# Execute o arquivo: api/database/schema.sql
```

#### 1.3. Configuração da API
```bash
# Edite o arquivo: api/config/database.php
# Configure suas credenciais do MySQL:
DB_HOST = "localhost"
DB_NAME = "mei_financeiro_db" 
DB_USER = "root"
DB_PASS = ""  # Vazio por padrão no XAMPP
```

#### 1.4. Teste da API
```bash
# Acesse: http://localhost/mei-financeiro-saas/api/
# Deve retornar: {"message": "MEI Financeiro API v1.0", "status": "running"}
```

### 2. Configuração do Frontend (Flutter)

#### 2.1. Instalação do Flutter
```bash
# Baixe o Flutter SDK: https://flutter.dev/docs/get-started/install
# Adicione o Flutter ao PATH do sistema
# Verifique a instalação:
flutter doctor
```

#### 2.2. Configuração do Projeto
```bash
# Navegue até o diretório do projeto
cd mei-financeiro-saas/flutter_app

# Instale as dependências
flutter pub get

# Verifique se não há erros
flutter analyze
```

#### 2.3. Configuração da API URL
```bash
# Edite: flutter_app/lib/services/api_service.dart
# Altere a baseURL se necessário:
static const String baseURL = 'http://localhost/mei-financeiro-saas/api';
```

### 3. Executando o Projeto

#### 3.1. Backend
```bash
# 1. Inicie o XAMPP (Apache + MySQL)
# 2. Verifique se a API está respondendo:
#    http://localhost/mei-financeiro-saas/api/

# 3. Teste endpoints básicos:
# POST http://localhost/mei-financeiro-saas/api/auth/register
# POST http://localhost/mei-financeiro-saas/api/auth/login
```

#### 3.2. Frontend
```bash
cd flutter_app

# Para Android (emulador ou dispositivo)
flutter run

# Para Web
flutter run -d chrome

# Para iOS (macOS apenas)
flutter run -d ios
```

## 🧪 Testando o Sistema

### 1. Teste de Registro
```bash
# Use um app como Postman ou Insomnia
# POST http://localhost/mei-financeiro-saas/api/auth/register
# Body (JSON):
{
  "name": "João Silva",
  "email": "joao@email.com",
  "password": "123456",
  "business_name": "João Delivery"
}
```

### 2. Teste de Login
```bash
# POST http://localhost/mei-financeiro-saas/api/auth/login
# Body (JSON):
{
  "email": "joao@email.com",
  "password": "123456"
}
```

### 3. Fluxo Completo do App
1. Abra o app Flutter
2. Registre um novo usuário
3. Faça login
4. Adicione algumas transações
5. Visualize o dashboard
6. Teste o sistema de assinatura

## 🔧 Solução de Problemas

### Problema: API não responde
**Solução**: 
- Verifique se Apache está rodando no XAMPP
- Confirme se o projeto está em `C:\xampp\htdocs\mei-financeiro-saas`

### Problema: Erro de conexão com banco
**Solução**:
- Verifique se MySQL está rodando
- Confirme as credenciais em `api/config/database.php`
- Teste a conexão via phpMyAdmin

### Problema: Flutter não compila
**Solução**:
```bash
# Limpe o cache
flutter clean

# Reinstale dependências  
flutter pub get

# Verifique problemas
flutter doctor
```

### Problema: CORS no navegador
**Solução**: O middleware CORS já está configurado, mas se necessário:
- Use `flutter run -d chrome --web-browser-flag "--disable-web-security"`

## 📚 Estrutura de Pastas

```
mei-financeiro-saas/
├── api/                     # Backend PHP
│   ├── controllers/         # Lógica de negócio
│   ├── models/             # Modelos de dados
│   ├── middleware/         # Middlewares
│   ├── config/             # Configurações
│   ├── database/           # Schema SQL
│   └── index.php           # Ponto de entrada
│
├── flutter_app/            # Frontend Flutter
│   ├── lib/
│   │   ├── main.dart      # Entrada do app
│   │   ├── models/        # Modelos Flutter
│   │   ├── providers/     # State management
│   │   ├── screens/       # Telas
│   │   ├── widgets/       # Componentes
│   │   ├── services/      # API services
│   │   └── utils/         # Utilitários
│   └── pubspec.yaml       # Dependências
│
└── docs/                  # Documentação
```

## 🌍 Deploy para Produção

### Backend (PHP)
```bash
# 1. Configure um servidor web (Apache/Nginx)
# 2. Configure MySQL em produção
# 3. Atualize as URLs da API
# 4. Configure SSL/HTTPS
# 5. Configure backup do banco
```

### Frontend (Flutter)
```bash
# Android (Google Play)
flutter build appbundle

# iOS (App Store) 
flutter build ios

# Web (Hosting)
flutter build web
```

## 💡 Próximos Passos

1. **Gateway de Pagamento**: Integrar Mercado Pago, PicPay
2. **Notificações Push**: Firebase Cloud Messaging
3. **Relatórios PDF**: Geração automática
4. **Backup**: Sincronização na nuvem
5. **Analytics**: Métricas de uso

---

### 📞 Suporte

Para dúvidas ou problemas:
- Verifique a documentação da API
- Confira os logs do PHP no XAMPP
- Use `flutter doctor` para diagnóstico

**Status do Projeto**: ✅ Completo e Funcional  
**Versão**: 1.0  
**Última Atualização**: 2024