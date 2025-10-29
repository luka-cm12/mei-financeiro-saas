import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

class TestApiScreen extends StatefulWidget {
  const TestApiScreen({super.key});

  @override
  State<TestApiScreen> createState() => _TestApiScreenState();
}

class _TestApiScreenState extends State<TestApiScreen> {
  String _result = '';
  bool _isLoading = false;

  void _addResult(String text) {
    setState(() {
      _result += '\n\n${DateTime.now().toString().substring(11, 19)}\n$text';
    });
  }

  Future<void> _testFullConnection() async {
    setState(() {
      _isLoading = true;
      _result = 'Iniciando teste completo...';
    });

    try {
      // 1. Testar detecção de URL
      _addResult('🔍 Detectando melhor URL...');
      ApiService.resetUrl();
      final baseUrl = await ApiService.baseUrl;
      _addResult('✅ URL detectada: $baseUrl');

      // 2. Testar conexão
      _addResult('🔌 Testando conexão...');
      final connResponse = await ApiService.get('auth/login.php?check=1');
      _addResult('Conexão: ${connResponse.success ? '✅' : '❌'}\n${connResponse.message}');

      if (!connResponse.success) {
        _addResult('❌ Parando teste - conexão falhou');
        return;
      }

      // 3. Testar registro
      _addResult('📝 Testando registro...');
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      final testEmail = 'teste_flutter_$timestamp@email.com';
      
      final registerResult = await AuthService.register(
        name: 'Teste Flutter',
        email: testEmail,
        password: '123456',
      );

      _addResult('Registro: ${registerResult.success ? '✅' : '❌'}\n${registerResult.message}');
      
      if (!registerResult.success) {
        _addResult('❌ Registro falhou');
        return;
      }

      // 4. Testar login
      _addResult('🚀 Testando login...');
      final loginResult = await AuthService.login(
        email: testEmail,
        password: '123456',
      );

      _addResult('Login: ${loginResult.success ? '✅' : '❌'}\n${loginResult.message}');
      
      if (loginResult.success) {
        _addResult('🎉 TODOS OS TESTES PASSARAM!\nToken: ${loginResult.token != null ? 'RECEBIDO' : 'ERRO'}');
      }

    } catch (e) {
      _addResult('❌ ERRO GERAL: $e');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  void _copyToClipboard() {
    Clipboard.setData(ClipboardData(text: _result));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Log copiado para área de transferência')),
    );
  }

  void _clearLog() {
    setState(() {
      _result = '';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Teste Completo API'),
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.copy),
            onPressed: _copyToClipboard,
          ),
          IconButton(
            icon: const Icon(Icons.clear),
            onPressed: _clearLog,
          ),
        ],
      ),
      body: Column(
        children: [
          // Botão de teste
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            child: _isLoading
                ? const Column(
                    children: [
                      CircularProgressIndicator(),
                      SizedBox(height: 16),
                      Text('Executando testes...'),
                    ],
                  )
                : ElevatedButton.icon(
                    onPressed: _testFullConnection,
                    icon: const Icon(Icons.play_arrow),
                    label: const Text('🧪 EXECUTAR TESTE COMPLETO'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.green,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.all(16),
                    ),
                  ),
          ),

          // Status atual
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Card(
              color: Colors.blue.shade50,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'ℹ️ Como funciona este teste:',
                      style: TextStyle(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    const Text('1. Detecta automaticamente a melhor URL'),
                    const Text('2. Testa a conexão com a API'),
                    const Text('3. Registra um usuário de teste'),
                    const Text('4. Faz login com o usuário'),
                    const Text('5. Mostra se tudo funcionou'),
                  ],
                ),
              ),
            ),
          ),

          const SizedBox(height: 16),

          // Log
          Expanded(
            child: Container(
              margin: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                border: Border.all(color: Colors.grey),
                borderRadius: BorderRadius.circular(8),
              ),
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Text(
                  _result.isEmpty ? 'Clique em "EXECUTAR TESTE COMPLETO" para começar...' : _result,
                  style: const TextStyle(
                    fontFamily: 'Courier New',
                    fontSize: 12,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}