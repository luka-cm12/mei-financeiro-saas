# MEI Financeiro SaaS

Um sistema completo de gestão financeira desenvolvido especificamente para Microempreendedores Individuais (MEIs) e autônomos.

## 💰 Funcionalidades

- **Controle de Transações**: Cadastro e gerenciamento de receitas e despesas
- **Cálculo Automático de Lucro**: Visualização em tempo real do resultado financeiro
- **Categorização**: Organize suas transações por categorias personalizáveis
- **Dashboard Intuitivo**: Visão geral das finanças com gráficos e indicadores
- **Relatórios Mensais**: Análise detalhada da performance financeira
- **Sistema de Assinatura**: Modelo SaaS com período trial gratuito
- **Multiplataforma**: Disponível para Android, iOS e Web

## 🛠 Tecnologias

### Backend (API REST)
- **PHP 8+** - Linguagem de programação
- **MySQL** - Banco de dados
- **JWT** - Autenticação
- **XAMPP** - Servidor de desenvolvimento

### Frontend (Aplicativo)
- **Flutter 3.35+** - Framework multiplataforma
- **Provider** - Gerenciamento de estado
- **HTTP/Dio** - Comunicação com API
- **Material Design** - Interface de usuário

## 📱 Instalação e Configuração

### 1. Configurar Backend (API)

#### Pré-requisitos
- XAMPP instalado
- PHP 8.0 ou superior
- MySQL 5.7 ou superior

#### Passos
1. Clone o repositório no diretório `htdocs` do XAMPP:
```bash
cd C:\xampp\htdocs
git clone [url-do-repositorio] mei-financeiro-saas
```

2. Inicie o XAMPP (Apache e MySQL)

3. Crie o banco de dados:
   - Acesse `http://localhost/phpmyadmin`
   - Crie um novo banco chamado `mei_financeiro`
   - Execute o script `api/database/schema.sql`

4. Configure as credenciais do banco:
   - Edite `api/config/Database.php` se necessário

5. Teste a API:
   - Acesse `http://localhost/mei-financeiro-saas/api`

### 2. Configurar Frontend (Flutter)

#### Pré-requisitos
- Flutter SDK 3.35 ou superior
- Dart SDK
- Android Studio/VS Code
- Emulador Android ou dispositivo físico

#### Passos
1. Navegue para a pasta do Flutter:
```bash
cd mei-financeiro-saas/flutter_app
```

2. Instale as dependências:
```bash
flutter pub get
```

3. Configure a URL da API:
   - Edite `lib/services/api_service.dart`
   - Para emulador Android: `http://10.0.2.2/mei-financeiro-saas/api`
   - Para dispositivo físico: `http://[IP_DO_SEU_PC]/mei-financeiro-saas/api`

4. Execute o aplicativo:
```bash
flutter run
```

## 🚀 Estrutura do Projeto

```
mei-financeiro-saas/
├── api/                          # Backend PHP
│   ├── config/                   # Configurações
│   ├── controllers/              # Controladores da API
│   ├── models/                   # Modelos de dados
│   ├── middleware/               # Middleware (auth, CORS)
│   ├── database/                 # Scripts SQL
│   └── index.php                 # Ponto de entrada
├── flutter_app/                  # Frontend Flutter
│   ├── lib/
│   │   ├── models/               # Modelos de dados
│   │   ├── providers/            # Gerenciamento de estado
│   │   ├── screens/              # Telas do app
│   │   ├── services/             # Serviços (API)
│   │   ├── widgets/              # Componentes reutilizáveis
│   │   └── utils/                # Utilitários e temas
│   └── pubspec.yaml             # Dependências Flutter
└── README.md                    # Este arquivo
```

## 📊 Endpoints da API

### Autenticação
- `POST /api/auth/register` - Registrar novo usuário
- `POST /api/auth/login` - Fazer login
- `POST /api/auth/refresh` - Renovar token

### Transações
- `GET /api/transactions` - Listar transações
- `POST /api/transactions` - Criar transação
- `PUT /api/transactions/{id}` - Atualizar transação
- `DELETE /api/transactions/{id}` - Deletar transação

### Categorias
- `GET /api/categories` - Listar categorias
- `POST /api/categories` - Criar categoria

### Relatórios
- `GET /api/reports/monthly` - Relatório mensal

### Usuário
- `GET /api/user/profile` - Dados do perfil
- `PUT /api/user/profile` - Atualizar perfil

## 💳 Modelo de Negócio

### Assinatura
- **Trial Gratuito**: 30 dias com todas as funcionalidades
- **Plano Pro**: R$ 19,90/mês
  - Transações ilimitadas
  - Relatórios avançados
  - Gráficos detalhados
  - Backup automático
  - Suporte prioritário

### Público-Alvo
- Microempreendedores Individuais (MEIs)
- Autônomos que vendem via WhatsApp/Instagram
- Pequenos empreendedores sem conhecimento contábil avançado

## 🔧 Desenvolvimento

### Comandos Úteis

#### Backend
```bash
# Verificar sintaxe PHP
php -l api/index.php

# Executar testes (se configurados)
php vendor/bin/phpunit
```

#### Frontend
```bash
# Executar em modo debug
flutter run

# Build para produção
flutter build apk --release

# Executar testes
flutter test

# Analisar código
flutter analyze
```

### Configurações de Desenvolvimento

#### API Local
Para desenvolvimento, a API está configurada para:
- URL: `http://localhost/mei-financeiro-saas/api`
- CORS habilitado para `localhost:3000`, `localhost:8080`
- Headers de debug habilitados

#### Flutter Debug
Para desenvolvimento, configure:
- Hot reload habilitado
- Debug banner removido
- Logs detalhados no console

## 📱 Funcionalidades Detalhadas

### 1. Sistema de Autenticação
- Registro com dados pessoais e do negócio
- Login seguro com JWT
- Período trial de 30 dias
- Controle de acesso por assinatura

### 2. Gestão de Transações
- Cadastro rápido de receitas e despesas
- Categorização automática e manual
- Múltiplos métodos de pagamento
- Filtros por período, categoria e tipo

### 3. Dashboard Financeiro
- Visão geral do período atual
- Cards com receitas, despesas e lucro
- Lista das últimas transações
- Indicadores visuais por cores

### 4. Relatórios e Análises
- Relatórios mensais detalhados
- Gráficos de evolução (em desenvolvimento)
- Análise por categorias
- Sugestões de melhorias (planejado)

### 5. Sistema de Assinatura
- Trial gratuito de 30 dias
- Notificações de expiração
- Bloqueio de funcionalidades após expiração
- Integração com gateway de pagamento (planejado)

## 🔐 Segurança

- Autenticação JWT com expiração
- Validação de entrada em todos os endpoints
- Sanitização de dados
- Controle de acesso baseado em assinatura
- Headers de segurança CORS configurados

## 🚧 Roadmap

### Versão 2.0 (Próxima)
- [ ] Gráficos interativos com fl_chart
- [ ] Integração com APIs bancárias
- [ ] Backup automático na nuvem
- [ ] Notificações push
- [ ] Modo offline

### Versão 3.0 (Futura)
- [ ] Relatórios em PDF
- [ ] Integração com contadores
- [ ] Análises com IA
- [ ] Multi-empresas
- [ ] API para integrações

## 📞 Suporte

Para dúvidas, sugestões ou problemas:
- 📧 Email: contato@meifinanceiro.com.br
- 💬 WhatsApp: (11) 99999-9999
- 🌐 Site: www.meifinanceiro.com.br

## 📝 Licença

Este projeto é proprietário e destinado ao uso comercial da MEI Financeiro SaaS.

---

**MEI Financeiro SaaS** - Simplificando a gestão financeira para microempreendedores! 💼✨