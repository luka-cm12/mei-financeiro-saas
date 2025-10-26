import '../models/user.dart';
import 'api_service.dart';

class AuthService {
  static Future<AuthResult> register({
    required String name,
    required String email,
    required String password,
    String? phone,
    String? businessName,
    String? businessType,
    String? cnpj,
  }) async {
    final response = await ApiService.post('auth/register', {
      'name': name,
      'email': email,
      'password': password,
      'phone': phone,
      'business_name': businessName,
      'business_type': businessType,
      'cnpj': cnpj,
    });

    if (response.success && response.data['token'] != null) {
      await ApiService.saveToken(response.data['token']);
      return AuthResult(
        success: true,
        message: response.message,
        token: response.data['token'],
        user: null, // Usuário será carregado após o login
      );
    }

    return AuthResult(
      success: false,
      message: response.message,
      token: null,
      user: null,
    );
  }

  static Future<AuthResult> login({
    required String email,
    required String password,
  }) async {
    final response = await ApiService.post('auth/login', {
      'email': email,
      'password': password,
    });

    if (response.success && response.data['token'] != null) {
      await ApiService.saveToken(response.data['token']);
      
      User? user;
      if (response.data['user'] != null) {
        user = User.fromJson(response.data['user']);
      }

      return AuthResult(
        success: true,
        message: response.message,
        token: response.data['token'],
        user: user,
      );
    }

    return AuthResult(
      success: false,
      message: response.message,
      token: null,
      user: null,
    );
  }

  static Future<AuthResult> refreshToken() async {
    final response = await ApiService.post('auth/refresh', {});

    if (response.success && response.data['token'] != null) {
      await ApiService.saveToken(response.data['token']);
      
      User? user;
      if (response.data['user'] != null) {
        user = User.fromJson(response.data['user']);
      }

      return AuthResult(
        success: true,
        message: response.message,
        token: response.data['token'],
        user: user,
      );
    }

    return AuthResult(
      success: false,
      message: response.message,
      token: null,
      user: null,
    );
  }

  static Future<void> logout() async {
    await ApiService.removeToken();
  }

  static Future<bool> isLoggedIn() async {
    final token = await ApiService.getToken();
    return token != null;
  }

  static Future<ApiResponse> getProfile() async {
    return await ApiService.get('user/profile');
  }

  static Future<ApiResponse> updateProfile(Map<String, dynamic> data) async {
    return await ApiService.put('user/profile', data);
  }

  static Future<ApiResponse> getSubscriptionStatus() async {
    return await ApiService.get('user/subscription');
  }
}

class AuthResult {
  final bool success;
  final String message;
  final String? token;
  final User? user;

  AuthResult({
    required this.success,
    required this.message,
    this.token,
    this.user,
  });
}