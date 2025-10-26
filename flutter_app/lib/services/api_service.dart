import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  // Para desenvolvimento local
  static const String baseUrl = 'http://localhost/mei-financeiro-saas/api';
  
  // Para produção, altere para:
  // static const String baseUrl = 'https://sua-api.com.br/api';
  
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
      final response = await http.get(
        Uri.parse('$baseUrl/$endpoint'),
        headers: await _headersWithAuth,
      );
      
      return _handleResponse(response);
    } catch (e) {
      return ApiResponse(
        success: false,
        message: 'Erro de conexão: $e',
        data: null,
      );
    }
  }
  
  static Future<ApiResponse> post(String endpoint, Map<String, dynamic> data) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/$endpoint'),
        headers: await _headersWithAuth,
        body: json.encode(data),
      );
      
      return _handleResponse(response);
    } catch (e) {
      return ApiResponse(
        success: false,
        message: 'Erro de conexão: $e',
        data: null,
      );
    }
  }
  
  static Future<ApiResponse> put(String endpoint, Map<String, dynamic> data) async {
    try {
      final response = await http.put(
        Uri.parse('$baseUrl/$endpoint'),
        headers: await _headersWithAuth,
        body: json.encode(data),
      );
      
      return _handleResponse(response);
    } catch (e) {
      return ApiResponse(
        success: false,
        message: 'Erro de conexão: $e',
        data: null,
      );
    }
  }
  
  static Future<ApiResponse> delete(String endpoint) async {
    try {
      final response = await http.delete(
        Uri.parse('$baseUrl/$endpoint'),
        headers: await _headersWithAuth,
      );
      
      return _handleResponse(response);
    } catch (e) {
      return ApiResponse(
        success: false,
        message: 'Erro de conexão: $e',
        data: null,
      );
    }
  }
  
  static ApiResponse _handleResponse(http.Response response) {
    final Map<String, dynamic> responseData = response.body.isNotEmpty
        ? json.decode(response.body)
        : {};
    
    final bool success = response.statusCode >= 200 && response.statusCode < 300;
    
    return ApiResponse(
      success: success,
      statusCode: response.statusCode,
      message: responseData['message'] ?? (success ? 'Sucesso' : 'Erro'),
      data: responseData,
    );
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