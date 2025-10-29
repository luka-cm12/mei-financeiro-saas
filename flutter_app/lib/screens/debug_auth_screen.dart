import 'package:flutter/material.dart';
import '../services/auth_service.dart';
import '../services/api_service.dart';

class DebugAuthScreen extends StatefulWidget {
  const DebugAuthScreen({super.key});

  @override
  State<DebugAuthScreen> createState() => _DebugAuthScreenState();
}

class _DebugAuthScreenState extends State<DebugAuthScreen> {
  String _debugInfo = 'Pronto para teste...';
  bool _isLoading = false;

  final _emailController = TextEditingController(text: 'teste@email.com');
  final _passwordController = TextEditingController(text: '123456');
  final _nameController = TextEditingController(text: 'Usuário Teste Flutter');

  void _addLog(String message) {
    setState(() {
      _debugInfo += '\n\n[${DateTime.now().toString().substring(11, 19)}]\n$message';
    });
  }

  @override
  void initState() {
    super.initState();
    _initializeDebug();
  }

  Future<void> _initializeDebug() async {
    final baseUrl = await ApiService.baseUrl;
    _addLog('🚀 Debug iniciado\nURL Base: $baseUrl');
  }

  Future<void> _testConnection() async {
    setState(() => _isLoading = true);
    
    try {
      _addLog('🔌 Testando todas as URLs possíveis...');
      
      // Resetar URL para forçar novo teste
      ApiService.resetUrl();
      
      final baseUrl = await ApiService.baseUrl;
      _addLog('URL escolhida: $baseUrl');
      
      final response = await ApiService.get('auth/login.php?check=1');
      
      _addLog('Resposta de conexão:\n'
          'Success: ${response.success}\n'
          'Status: ${response.statusCode}\n'
          'Message: ${response.message}\n'
          'Data: ${response.data}');
          
    } catch (e) {
      _addLog('❌ Erro na conexão: $e');
    }
    
    setState(() => _isLoading = false);
  }

  Future<void> _testRegister() async {
    setState(() => _isLoading = true);
    
    try {
      _addLog('📝 Testando registro...');
      
      // Gerar email único
      final uniqueEmail = 'teste${DateTime.now().millisecondsSinceEpoch}@flutter.com';
      
      final result = await AuthService.register(
        name: _nameController.text,
        email: uniqueEmail,
        password: _passwordController.text,
      );
      
      _addLog('Resultado do registro:\n'
          'Success: ${result.success}\n'
          'Message: ${result.message}\n'
          'Token: ${result.token != null ? "RECEBIDO" : "NÃO RECEBIDO"}\n'
          'User: ${result.user?.name ?? "NULL"}');
          
      if (result.success) {
        _emailController.text = uniqueEmail;
      }
          
    } catch (e) {
      _addLog('❌ Erro no registro: $e');
    }
    
    setState(() => _isLoading = false);
  }

  Future<void> _testLogin() async {
    setState(() => _isLoading = true);
    
    try {
      _addLog('🚀 Testando login...');
      
      final result = await AuthService.login(
        email: _emailController.text,
        password: _passwordController.text,
      );
      
      _addLog('Resultado do login:\n'
          'Success: ${result.success}\n'
          'Message: ${result.message}\n'
          'Token: ${result.token != null ? "RECEBIDO" : "NÃO RECEBIDO"}\n'
          'User: ${result.user?.name ?? "NULL"}');
          
    } catch (e) {
      _addLog('❌ Erro no login: $e');
    }
    
    setState(() => _isLoading = false);
  }

  Future<void> _testDirectAPI() async {
    setState(() => _isLoading = true);
    
    try {
      _addLog('🔧 Testando API diretamente...');
      
      final response = await ApiService.post('auth/login.php', {
        'email': _emailController.text,
        'password': _passwordController.text,
      });
      
      _addLog('Resposta direta da API:\n'
          'Success: ${response.success}\n'
          'Status: ${response.statusCode}\n'
          'Message: ${response.message}\n'
          'Data completa: ${response.data}');
          
    } catch (e) {
      _addLog('❌ Erro na API direta: $e');
    }
    
    setState(() => _isLoading = false);
  }

  void _clearLog() {
    setState(() {
      _debugInfo = 'Log limpo...';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Debug Autenticação'),
        backgroundColor: Colors.blue,
        foregroundColor: Colors.white,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          children: [
            // Campos de teste
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  children: [
                    TextField(
                      controller: _nameController,
                      decoration: const InputDecoration(
                        labelText: 'Nome',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _emailController,
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _passwordController,
                      decoration: const InputDecoration(
                        labelText: 'Senha',
                        border: OutlineInputBorder(),
                      ),
                      obscureText: true,
                    ),
                  ],
                ),
              ),
            ),
            
            const SizedBox(height: 16),
            
            // Botões de teste
            if (_isLoading)
              const CircularProgressIndicator()
            else
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ElevatedButton.icon(
                    onPressed: _testConnection,
                    icon: const Icon(Icons.wifi),
                    label: const Text('Conexão'),
                  ),
                  ElevatedButton.icon(
                    onPressed: _testRegister,
                    icon: const Icon(Icons.person_add),
                    label: const Text('Registro'),
                  ),
                  ElevatedButton.icon(
                    onPressed: _testLogin,
                    icon: const Icon(Icons.login),
                    label: const Text('Login'),
                  ),
                  ElevatedButton.icon(
                    onPressed: _testDirectAPI,
                    icon: const Icon(Icons.api),
                    label: const Text('API Direta'),
                  ),
                  ElevatedButton.icon(
                    onPressed: _clearLog,
                    icon: const Icon(Icons.clear),
                    label: const Text('Limpar'),
                  ),
                ],
              ),
            
            const SizedBox(height: 16),
            
            // Log de debug
            Expanded(
              child: Card(
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16.0),
                  child: SingleChildScrollView(
                    child: Text(
                      _debugInfo,
                      style: const TextStyle(
                        fontFamily: 'Courier',
                        fontSize: 12,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}