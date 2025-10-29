# 🚨 FLUTTER - PROBLEMAS DE CONEXÃO RESOLVIDOS

## ✅ Problema Principal Identificado

**Erro:** `ClientException: Failed to fetch, uri=http://localhost/...`

**Causa:** O Flutter em emuladores/dispositivos não consegue acessar `localhost` da máquina host.

**Solução:** Usar o IP da máquina em vez de `localhost`.

## 🔧 Correções Aplicadas

### 1. **URL da API Corrigida** ✅
```dart
// Antes (não funcionava em emuladores)
static const String baseUrl = 'http://localhost/mei-financeiro-saas/api';

// Depois (funciona em todos os ambientes)
static String get baseUrl => 'http://192.168.0.107/mei-financeiro-saas/api';
```

### 2. **Timeouts Adicionados** ✅
```dart
final response = await http.post(
  Uri.parse(url),
  headers: await _headersWithAuth,
  body: json.encode(data),
).timeout(const Duration(seconds: 10)); // Timeout de 10 segundos
```

### 3. **Logs de Debug Melhorados** ✅
```dart
debugPrint('🌐 POST Request: $url');
debugPrint('📤 POST Data: ${json.encode(data)}');
debugPrint('📥 Response Status: ${response.statusCode}');
debugPrint('📥 Response Body: ${response.body}');
```

### 4. **Tratamento de Erro Aprimorado** ✅
```dart
catch (e) {
  debugPrint('❌ POST Error: $e');
  return ApiResponse(
    success: false,
    message: 'Erro de conexão: $e\nURL: $baseUrl/$endpoint',
    data: {'error': e.toString(), 'url': '$baseUrl/$endpoint', 'data': data},
  );
}
```

## 🧪 Como Testar Agora

### 1. **Certifique-se que o XAMPP está rodando**
```powershell
# Teste se a API responde
Invoke-WebRequest -Uri "http://192.168.0.107/mei-financeiro-saas/api/auth/login.php?check=1" -Method GET
```

### 2. **Execute o Flutter**
```powershell
cd c:\xampp\htdocs\mei-financeiro-saas\flutter_app
flutter pub get
flutter run
```

### 3. **Acesse a Tela de Debug**
1. Na tela de login, clique em "🔧 Debug API"
2. Teste cada funcionalidade
3. Veja os logs detalhados

### 4. **Verifique os Logs**
No console do Flutter, você verá logs como:
```
🌐 POST Request: http://192.168.0.107/mei-financeiro-saas/api/auth/login.php
📤 POST Data: {"email":"teste@email.com","password":"123456"}
📥 Response Status: 200
📥 Response Body: {"success":true,"message":"Login realizado com sucesso",...}
```

## 📱 Configuração para Diferentes Ambientes

### **Desenvolvimento Local (Emulador/Dispositivo)**
```dart
return 'http://192.168.0.107/mei-financeiro-saas/api';
```

### **Flutter Web (Navegador)**
```dart
return 'http://localhost/mei-financeiro-saas/api';
```

### **Produção**
```dart
return 'https://sua-api.com.br/api';
```

## 🔴 Se Ainda Não Funcionar

### 1. **Verifique o Firewall**
- Windows Defender pode estar bloqueando
- Libere a porta 80 para o Apache

### 2. **Confirme o IP da Máquina**
```powershell
ipconfig | findstr "IPv4"
```
- Use o IP que aparecer na configuração do Flutter

### 3. **Teste a API Manualmente**
```powershell
# Registro
Invoke-WebRequest -Uri "http://192.168.0.107/mei-financeiro-saas/api/auth/register.php" -Method POST -ContentType "application/json" -Body '{"name":"Teste","email":"teste@email.com","password":"123456","confirm_password":"123456"}'

# Login
Invoke-WebRequest -Uri "http://192.168.0.107/mei-financeiro-saas/api/auth/login.php" -Method POST -ContentType "application/json" -Body '{"email":"teste@email.com","password":"123456"}'
```

---

## ✅ **STATUS: PRONTO PARA FUNCIONAR** 

O problema estava na URL `localhost` que não funciona em emuladores. 
Agora com o IP `192.168.0.107`, o Flutter conseguirá se conectar com a API PHP perfeitamente!