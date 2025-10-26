import 'package:flutter/foundation.dart';
import '../models/user.dart';
import '../services/auth_service.dart';

class AuthProvider with ChangeNotifier {
  User? _user;
  bool _isLoading = false;
  String? _errorMessage;
  bool _isLoggedIn = false;

  // Getters
  User? get user => _user;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get isLoggedIn => _isLoggedIn;
  bool get hasActiveSubscription => _user?.hasActiveSubscription ?? false;

  // Inicializar verificando se usuário já está logado
  Future<void> initialize() async {
    _setLoading(true);
    
    try {
      final isLoggedIn = await AuthService.isLoggedIn();
      if (isLoggedIn) {
        await refreshUserData();
      }
      _isLoggedIn = isLoggedIn;
    } catch (e) {
      _setError('Erro ao inicializar: $e');
    } finally {
      _setLoading(false);
    }
  }

  // Login
  Future<bool> login(String email, String password) async {
    _setLoading(true);
    _clearError();

    try {
      final result = await AuthService.login(
        email: email,
        password: password,
      );

      if (result.success) {
        _user = result.user;
        _isLoggedIn = true;
        _setLoading(false);
        notifyListeners();
        return true;
      } else {
        _setError(result.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao fazer login: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  // Registro
  Future<bool> register({
    required String name,
    required String email,
    required String password,
    String? phone,
    String? businessName,
    String? businessType,
    String? cnpj,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      final result = await AuthService.register(
        name: name,
        email: email,
        password: password,
        phone: phone,
        businessName: businessName,
        businessType: businessType,
        cnpj: cnpj,
      );

      if (result.success) {
        // Após registro, fazer login para obter dados completos
        final loginSuccess = await login(email, password);
        return loginSuccess;
      } else {
        _setError(result.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao registrar: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  // Logout
  Future<void> logout() async {
    _setLoading(true);
    
    try {
      await AuthService.logout();
      _user = null;
      _isLoggedIn = false;
      _clearError();
    } catch (e) {
      _setError('Erro ao fazer logout: $e');
    } finally {
      _setLoading(false);
    }
  }

  // Atualizar dados do usuário
  Future<void> refreshUserData() async {
    try {
      final response = await AuthService.getProfile();
      
      if (response.success && response.data['profile'] != null) {
        _user = User.fromJson(response.data['profile']);
        notifyListeners();
      } else if (response.isUnauthorized) {
        // Token expirado, fazer logout
        await logout();
      }
    } catch (e) {
      _setError('Erro ao atualizar dados: $e');
    }
  }

  // Atualizar perfil
  Future<bool> updateProfile(Map<String, dynamic> data) async {
    _setLoading(true);
    _clearError();

    try {
      final response = await AuthService.updateProfile(data);
      
      if (response.success) {
        await refreshUserData();
        return true;
      } else {
        _setError(response.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao atualizar perfil: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  // Verificar status da assinatura
  Future<void> checkSubscriptionStatus() async {
    try {
      final response = await AuthService.getSubscriptionStatus();
      
      if (response.success) {
        // Atualizar dados do usuário com status da assinatura
        await refreshUserData();
      }
    } catch (e) {
      // Falha silenciosa para não afetar a UX
      debugPrint('Erro ao verificar assinatura: $e');
    }
  }

  // Métodos auxiliares
  void _setLoading(bool loading) {
    _isLoading = loading;
    notifyListeners();
  }

  void _setError(String error) {
    _errorMessage = error;
    _isLoading = false;
    notifyListeners();
  }

  void _clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  void clearError() => _clearError();
}