import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';
import 'package:local_auth/error_codes.dart' as auth_error;

enum BiometricType {
  none,
  fingerprint,
  face,
  both
}

class BiometricService {
  static final LocalAuthentication _localAuth = LocalAuthentication();
  
  /// Verifica se o dispositivo suporta biometria
  static Future<bool> isDeviceSupported() async {
    try {
      return await _localAuth.isDeviceSupported();
    } catch (e) {
      return false;
    }
  }
  
  /// Verifica se há biometrias disponíveis no dispositivo
  static Future<bool> canCheckBiometrics() async {
    try {
      return await _localAuth.canCheckBiometrics;
    } catch (e) {
      return false;
    }
  }
  
  /// Obtém os tipos de biometria disponíveis
  static Future<List<BiometricType>> getAvailableBiometrics() async {
    try {
      final List<BiometricType> availableBiometrics = <BiometricType>[];
      
      if (!await isDeviceSupported() || !await canCheckBiometrics()) {
        return [BiometricType.none];
      }
      
      final availableTypes = await _localAuth.getAvailableBiometrics();
      
      if (availableTypes.contains(BiometricType.fingerprint)) {
        availableBiometrics.add(BiometricType.fingerprint);
      }
      
      if (availableTypes.contains(BiometricType.face)) {
        availableBiometrics.add(BiometricType.face);
      }
      
      // Se tem ambos, adiciona a opção "both"
      if (availableBiometrics.contains(BiometricType.fingerprint) && 
          availableBiometrics.contains(BiometricType.face)) {
        availableBiometrics.add(BiometricType.both);
      }
      
      return availableBiometrics.isEmpty ? [BiometricType.none] : availableBiometrics;
    } catch (e) {
      return [BiometricType.none];
    }
  }
  
  /// Autentica usando biometria
  static Future<BiometricAuthResult> authenticate({
    required String reason,
    BiometricType? preferredType,
    bool biometricOnly = false,
  }) async {
    try {
      if (!await isDeviceSupported() || !await canCheckBiometrics()) {
        return BiometricAuthResult(
          success: false,
          errorMessage: 'Biometria não disponível neste dispositivo',
          errorType: BiometricErrorType.notAvailable,
        );
      }
      
      final bool didAuthenticate = await _localAuth.authenticate(
        localizedFallbackTitle: 'Use sua senha',
        authMessages: const [
          AndroidAuthMessages(
            signInTitle: 'Autenticação Biométrica',
            cancelButton: 'Cancelar',
            deviceCredentialsRequiredTitle: 'Credenciais necessárias',
            deviceCredentialsSetupDescription: 
                'Configure a autenticação biométrica nas configurações do dispositivo',
            goToSettingsButton: 'Configurações',
            goToSettingsDescription: 'Configure a biometria',
          ),
          IOSAuthMessages(
            cancelButton: 'Cancelar',
            goToSettingsButton: 'Configurações',
            goToSettingsDescription: 'Configure a biometria',
            lockOut: 'Reative a biometria',
          ),
        ],
        options: AuthenticationOptions(
          biometricOnly: biometricOnly,
          stickyAuth: true,
        ),
      );
      
      return BiometricAuthResult(
        success: didAuthenticate,
        errorMessage: didAuthenticate ? null : 'Autenticação falhou',
        errorType: didAuthenticate ? null : BiometricErrorType.authenticationFailed,
      );
      
    } on PlatformException catch (e) {
      String errorMessage;
      BiometricErrorType errorType;
      
      switch (e.code) {
        case auth_error.notAvailable:
          errorMessage = 'Biometria não disponível';
          errorType = BiometricErrorType.notAvailable;
          break;
        case auth_error.notEnrolled:
          errorMessage = 'Nenhuma biometria cadastrada no dispositivo';
          errorType = BiometricErrorType.notEnrolled;
          break;
        case auth_error.lockedOut:
          errorMessage = 'Muitas tentativas. Tente novamente mais tarde';
          errorType = BiometricErrorType.lockedOut;
          break;
        case auth_error.permanentlyLockedOut:
          errorMessage = 'Biometria bloqueada permanentemente';
          errorType = BiometricErrorType.permanentlyLockedOut;
          break;
        default:
          errorMessage = 'Erro na autenticação: ${e.message}';
          errorType = BiometricErrorType.unknown;
      }
      
      return BiometricAuthResult(
        success: false,
        errorMessage: errorMessage,
        errorType: errorType,
      );
    } catch (e) {
      return BiometricAuthResult(
        success: false,
        errorMessage: 'Erro inesperado: $e',
        errorType: BiometricErrorType.unknown,
      );
    }
  }
  
  /// Converte BiometricType para string
  static String biometricTypeToString(BiometricType type) {
    switch (type) {
      case BiometricType.fingerprint:
        return 'fingerprint';
      case BiometricType.face:
        return 'face';
      case BiometricType.both:
        return 'both';
      default:
        return 'none';
    }
  }
  
  /// Converte string para BiometricType
  static BiometricType stringToBiometricType(String type) {
    switch (type) {
      case 'fingerprint':
        return BiometricType.fingerprint;
      case 'face':
        return BiometricType.face;
      case 'both':
        return BiometricType.both;
      default:
        return BiometricType.none;
    }
  }
  
  /// Obtém o nome amigável do tipo de biometria
  static String getBiometricTypeName(BiometricType type) {
    switch (type) {
      case BiometricType.fingerprint:
        return 'Digital';
      case BiometricType.face:
        return 'Facial';
      case BiometricType.both:
        return 'Digital e Facial';
      default:
        return 'Nenhuma';
    }
  }
  
  /// Obtém o ícone do tipo de biometria
  static IconData getBiometricTypeIcon(BiometricType type) {
    switch (type) {
      case BiometricType.fingerprint:
        return Icons.fingerprint;
      case BiometricType.face:
        return Icons.face;
      case BiometricType.both:
        return Icons.security;
      default:
        return Icons.security_outlined;
    }
  }
}

class BiometricAuthResult {
  final bool success;
  final String? errorMessage;
  final BiometricErrorType? errorType;
  
  BiometricAuthResult({
    required this.success,
    this.errorMessage,
    this.errorType,
  });
}

enum BiometricErrorType {
  notAvailable,
  notEnrolled,
  lockedOut,
  permanentlyLockedOut,
  authenticationFailed,
  unknown,
}

