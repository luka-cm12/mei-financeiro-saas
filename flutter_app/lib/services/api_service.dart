import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  // Lista de URLs para testar em ordem de prioridade
  static final List<String> _possibleUrls = [
    'http://192.168.0.107/mei-financeiro-saas/api',  // IP da máquina
    'http://10.0.2.2/mei-financeiro-saas/api',       // Android emulator
    'http://localhost/mei-financeiro-saas/api',      // Localhost
    'http://127.0.0.1/mei-financeiro-saas/api',      // IP local
  ];
  
  static String? _workingUrl;
  
  // Reseta a URL para forçar novo teste
  static void resetUrl() {
    _workingUrl = null;
    debugPrint('🔄 URL resetada, será testada novamente');
  }
  
  // Detecta automaticamente a URL que funciona
  static Future<String> get baseUrl async {
    if (_workingUrl != null) {
      return _workingUrl!;
    }
    
    // Testa cada URL até encontrar uma que funcione
    for (String url in _possibleUrls) {
      if (await _testConnection(url)) {
        _workingUrl = url;
        debugPrint('🌐 URL funcionando encontrada: $url');
        return url;
      }
    }
    
    // Se nenhuma funcionar, usa a primeira como fallback
    _workingUrl = _possibleUrls.first;
    debugPrint('⚠️ Nenhuma URL funcionou, usando fallback: $_workingUrl');
    return _workingUrl!;
  }
  
  // Testa se uma URL está acessível
  static Future<bool> _testConnection(String baseUrl) async {
    try {
      debugPrint('🧪 Testando conexão: $baseUrl');
      final response = await http.get(
        Uri.parse('$baseUrl/auth/login.php?check=1'),
        headers: {'Accept': 'application/json'},
      ).timeout(const Duration(seconds: 3));
      
      final isWorking = response.statusCode == 200;
      debugPrint('${isWorking ? '✅' : '❌'} $baseUrl - Status: ${response.statusCode}');
      return isWorking;
    } catch (e) {
      debugPrint('❌ $baseUrl - Erro: $e');
      return false;
    }
  }
  
  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }
  
  static Future<void> saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }
  
  static Future<void> removeToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }
  
  static Map<String, String> get _headers => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  
  static Future<Map<String, String>> get _headersWithAuth async {
    final token = await getToken();
    return {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }
  
  // Métodos HTTP básicos
  static Future<ApiResponse> get(String endpoint) async {
    try {
      final base = await baseUrl;
      final url = '$base/$endpoint';
      debugPrint('🌐 GET Request: $url');
      
      final response = await http.get(
        Uri.parse(url),
        headers: await _headersWithAuth,
      ).timeout(const Duration(seconds: 10));
      
      debugPrint('📥 Response Status: ${response.statusCode}');
      debugPrint('📥 Response Body: ${response.body}');
      
      return _handleResponse(response);
    } catch (e) {
      debugPrint('❌ GET Error: $e');
      final base = await baseUrl;
      return ApiResponse(
        success: false,
        message: 'Erro de conexão: $e\nURL: $base/$endpoint',
        data: {'error': e.toString(), 'url': '$base/$endpoint'},
      );
    }
  }
  
  static Future<ApiResponse> post(String endpoint, Map<String, dynamic> data) async {
    try {
      final base = await baseUrl;
      final url = '$base/$endpoint';
      debugPrint('🌐 POST Request: $url');
      debugPrint('📤 POST Data: ${json.encode(data)}');
      
      final response = await http.post(
        Uri.parse(url),
        headers: await _headersWithAuth,
        body: json.encode(data),
      ).timeout(const Duration(seconds: 10));
      
      debugPrint('📥 Response Status: ${response.statusCode}');
      debugPrint('📥 Response Body: ${response.body}');
      
      return _handleResponse(response);
    } catch (e) {
      debugPrint('❌ POST Error: $e');
      final base = await baseUrl;
      return ApiResponse(
        success: false,
        message: 'Erro de conexão: $e\nURL: $base/$endpoint',
        data: {'error': e.toString(), 'url': '$base/$endpoint', 'data': data},
      );
    }
  }
  
  static Future<ApiResponse> put(String endpoint, Map<String, dynamic> data) async {
    try {
      final base = await baseUrl;
      final url = '$base/$endpoint';
      debugPrint('🌐 PUT Request: $url');
      
      final response = await http.put(
        Uri.parse(url),
        headers: await _headersWithAuth,
        body: json.encode(data),
      ).timeout(const Duration(seconds: 10));
      
      debugPrint('📥 PUT Response: ${response.statusCode}');
      return _handleResponse(response);
    } catch (e) {
      debugPrint('❌ PUT Error: $e');
      final base = await baseUrl;
      return ApiResponse(
        success: false,
        message: 'Erro de conexão: $e\nURL: $base/$endpoint',
        data: {'error': e.toString(), 'url': '$base/$endpoint'},
      );
    }
  }
  
  static Future<ApiResponse> delete(String endpoint) async {
    try {
      final base = await baseUrl;
      final url = '$base/$endpoint';
      debugPrint('🌐 DELETE Request: $url');
      
      final response = await http.delete(
        Uri.parse(url),
        headers: await _headersWithAuth,
      ).timeout(const Duration(seconds: 10));
      
      debugPrint('📥 DELETE Response: ${response.statusCode}');
      return _handleResponse(response);
    } catch (e) {
      debugPrint('❌ DELETE Error: $e');
      final base = await baseUrl;
      return ApiResponse(
        success: false,
        message: 'Erro de conexão: $e\nURL: $base/$endpoint',
        data: {'error': e.toString(), 'url': '$base/$endpoint'},
      );
    }
  }
  
  static ApiResponse _handleResponse(http.Response response) {
    try {
      final Map<String, dynamic> responseData = response.body.isNotEmpty
          ? json.decode(response.body)
          : {};
      
      final bool success = response.statusCode >= 200 && response.statusCode < 300 && 
                          (responseData['success'] == true || responseData['success'] == null);
      
      return ApiResponse(
        success: success && (responseData['success'] ?? true),
        statusCode: response.statusCode,
        message: responseData['message'] ?? (success ? 'Sucesso' : 'Erro'),
        data: responseData,
      );
    } catch (e) {
      return ApiResponse(
        success: false,
        statusCode: response.statusCode,
        message: 'Erro ao processar resposta: ${response.body}',
        data: {'error': e.toString(), 'raw_body': response.body},
      );
    }
  }
}

class ApiResponse {
  final bool success;
  final int? statusCode;
  final String message;
  final dynamic data;
  
  ApiResponse({
    required this.success,
    this.statusCode,
    required this.message,
    required this.data,
  });
  
  bool get isUnauthorized => statusCode == 401;
  bool get isForbidden => statusCode == 403;
  bool get isNotFound => statusCode == 404;
  bool get isServerError => statusCode != null && statusCode! >= 500;
}