import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';
import '../services/biometric_service.dart';

class TwoFactorProvider extends ChangeNotifier {
  bool _isLoading = false;
  String? _errorMessage;
  
  // Configurações de 2FA
  bool _twoFactorEnabled = false;
  BiometricType _biometricType = BiometricType.none;
  DateTime? _lastBiometricSetup;
  
  // Códigos de backup
  List<String> _backupCodes = [];
  bool _showBackupCodes = false;
  
  // Getters
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get twoFactorEnabled => _twoFactorEnabled;
  BiometricType get biometricType => _biometricType;
  DateTime? get lastBiometricSetup => _lastBiometricSetup;
  List<String> get backupCodes => _backupCodes;
  bool get showBackupCodes => _showBackupCodes;
  
  void _setLoading(bool loading) {
    _isLoading = loading;
    notifyListeners();
  }
  
  void _setError(String? error) {
    _errorMessage = error;
    notifyListeners();
  }
  
  void _clearError() {
    _errorMessage = null;
  }
  
  /// Carrega configurações de 2FA
  Future<void> loadSettings() async {
    _setLoading(true);
    _clearError();
    
    try {
      final response = await ApiService.get('two-factor/settings');
      
      if (response.success) {
        final settings = response.data['settings'];
        _twoFactorEnabled = settings['two_factor_enabled'] ?? false;
        _biometricType = BiometricService.stringToBiometricType(
          settings['biometric_type'] ?? 'none'
        );
        
        if (settings['last_biometric_setup'] != null) {
          _lastBiometricSetup = DateTime.parse(settings['last_biometric_setup']);
        }
        
        // Salvar preferências localmente
        await _saveLocalPreferences();
        
        notifyListeners();
      } else {
        _setError(response.message);
      }
    } catch (e) {
      _setError('Erro ao carregar configurações: $e');
    } finally {
      _setLoading(false);
    }
  }
  
  /// Ativa autenticação de dois fatores
  Future<bool> enableTwoFactor(BiometricType type) async {
    _setLoading(true);
    _clearError();
    
    try {
      // Primeiro, testar se a biometria funciona
      final authResult = await BiometricService.authenticate(
        reason: 'Configure a autenticação biométrica para maior segurança',
        preferredType: type,
      );
      
      if (!authResult.success) {
        _setError(authResult.errorMessage ?? 'Falha na autenticação biométrica');
        return false;
      }
      
      // Enviar para a API
      final response = await ApiService.post('two-factor/enable', {
        'biometric_type': BiometricService.biometricTypeToString(type),
        'device_id': await _getDeviceId(),
      });
      
      if (response.success) {
        _twoFactorEnabled = true;
        _biometricType = type;
        _lastBiometricSetup = DateTime.now();
        
        // Mostrar códigos de backup
        if (response.data['backup_codes'] != null) {
          _backupCodes = List<String>.from(response.data['backup_codes']);
          _showBackupCodes = true;
        }
        
        await _saveLocalPreferences();
        notifyListeners();
        return true;
      } else {
        _setError(response.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao ativar 2FA: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }
  
  /// Desativa autenticação de dois fatores
  Future<bool> disableTwoFactor({String? backupCode}) async {
    _setLoading(true);
    _clearError();
    
    try {
      final data = <String, dynamic>{};
      if (backupCode != null) {
        data['backup_code'] = backupCode;
      }
      
      final response = await ApiService.post('two-factor/disable', data);
      
      if (response.success) {
        _twoFactorEnabled = false;
        _biometricType = BiometricType.none;
        _lastBiometricSetup = null;
        _backupCodes.clear();
        _showBackupCodes = false;
        
        await _saveLocalPreferences();
        notifyListeners();
        return true;
      } else {
        _setError(response.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao desativar 2FA: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }
  
  /// Verifica autenticação biométrica
  Future<bool> verifyBiometric({String? reason}) async {
    if (!_twoFactorEnabled || _biometricType == BiometricType.none) {
      return true; // Se não está configurado, passa
    }
    
    try {
      final authResult = await BiometricService.authenticate(
        reason: reason ?? 'Autentique-se para continuar',
        preferredType: _biometricType,
      );
      
      if (authResult.success) {
        // Notificar a API sobre a verificação bem-sucedida
        final response = await ApiService.post('two-factor/verify-biometric', {
          'biometric_verified': true,
          'device_id': await _getDeviceId(),
        });
        
        return response.success;
      } else {
        _setError(authResult.errorMessage);
        return false;
      }
    } catch (e) {
      _setError('Erro na verificação biométrica: $e');
      return false;
    }
  }
  
  /// Verifica código de backup
  Future<bool> verifyBackupCode(String code) async {
    _setLoading(true);
    _clearError();
    
    try {
      final response = await ApiService.post('two-factor/verify-backup', {
        'backup_code': code,
      });
      
      if (response.success) {
        return true;
      } else {
        _setError(response.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao verificar código: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }
  
  /// Regenera códigos de backup
  Future<bool> regenerateBackupCodes() async {
    _setLoading(true);
    _clearError();
    
    try {
      final response = await ApiService.post('two-factor/regenerate-codes', {});
      
      if (response.success) {
        _backupCodes = List<String>.from(response.data['backup_codes']);
        _showBackupCodes = true;
        notifyListeners();
        return true;
      } else {
        _setError(response.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao regenerar códigos: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }
  
  /// Esconde códigos de backup
  void hideBackupCodes() {
    _showBackupCodes = false;
    _backupCodes.clear();
    notifyListeners();
  }
  
  /// Salva preferências localmente
  Future<void> _saveLocalPreferences() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('two_factor_enabled', _twoFactorEnabled);
    await prefs.setString('biometric_type', BiometricService.biometricTypeToString(_biometricType));
  }
  
  /// Carrega preferências locais
  Future<void> loadLocalPreferences() async {
    final prefs = await SharedPreferences.getInstance();
    _twoFactorEnabled = prefs.getBool('two_factor_enabled') ?? false;
    _biometricType = BiometricService.stringToBiometricType(
      prefs.getString('biometric_type') ?? 'none'
    );
    notifyListeners();
  }
  
  /// Obtém ID único do dispositivo
  Future<String> _getDeviceId() async {
    final prefs = await SharedPreferences.getInstance();
    String? deviceId = prefs.getString('device_id');
    
    if (deviceId == null) {
      // Gera um ID único para o dispositivo
      deviceId = DateTime.now().millisecondsSinceEpoch.toString();
      await prefs.setString('device_id', deviceId);
    }
    
    return deviceId;
  }
  
  /// Verifica se deve solicitar biometria
  bool shouldRequestBiometric() {
    return _twoFactorEnabled && _biometricType != BiometricType.none;
  }
}