import 'package:flutter/material.dart';
import '../services/subscription_service.dart';

class SubscriptionProvider extends ChangeNotifier {
  final SubscriptionService _subscriptionService = SubscriptionService();

  List<SubscriptionPlan> _plans = [];
  UserSubscription? _currentSubscription;
  bool _isLoading = false;
  String? _error;

  List<SubscriptionPlan> get plans => _plans;
  UserSubscription? get currentSubscription => _currentSubscription;
  bool get isLoading => _isLoading;
  String? get error => _error;

  bool get hasActiveSubscription => _currentSubscription?.isActive ?? false;
  bool get isPremium => hasActiveSubscription && !(_currentSubscription?.isFree ?? true);
  bool get isTrial => _currentSubscription?.isTrial ?? false;
  bool get isTrialExpiring => _currentSubscription?.isTrialExpiring ?? false;

  /// Carregar todos os planos
  Future<void> loadPlans() async {
    _setLoading(true);
    try {
      _plans = await _subscriptionService.getPlans();
      _error = null;
    } catch (e) {
      _error = e.toString();
      _plans = [];
    } finally {
      _setLoading(false);
    }
  }

  /// Carregar assinatura atual
  Future<void> loadCurrentSubscription() async {
    _setLoading(true);
    try {
      _currentSubscription = await _subscriptionService.getCurrentSubscription();
      _error = null;
    } catch (e) {
      _error = e.toString();
      _currentSubscription = null;
    } finally {
      _setLoading(false);
    }
  }

  /// Iniciar trial
  Future<bool> startTrial(String planSlug) async {
    _setLoading(true);
    try {
      final result = await _subscriptionService.subscribe(planSlug, trial: true);
      if (result['success'] == true) {
        await loadCurrentSubscription(); // Recarregar dados
        return true;
      }
      _error = result['message'] ?? 'Erro ao iniciar trial';
      return false;
    } catch (e) {
      _error = e.toString();
      return false;
    } finally {
      _setLoading(false);
    }
  }

  /// Fazer upgrade para plano pago
  Future<bool> upgrade(String planSlug) async {
    _setLoading(true);
    try {
      final result = await _subscriptionService.subscribe(planSlug, trial: false);
      if (result['success'] == true) {
        await loadCurrentSubscription(); // Recarregar dados
        return true;
      }
      _error = result['message'] ?? 'Erro ao fazer upgrade';
      return false;
    } catch (e) {
      _error = e.toString();
      return false;
    } finally {
      _setLoading(false);
    }
  }

  /// Cancelar assinatura
  Future<bool> cancelSubscription() async {
    _setLoading(true);
    try {
      final success = await _subscriptionService.cancelSubscription();
      if (success) {
        await loadCurrentSubscription(); // Recarregar dados
        return true;
      }
      _error = 'Erro ao cancelar assinatura';
      return false;
    } catch (e) {
      _error = e.toString();
      return false;
    } finally {
      _setLoading(false);
    }
  }

  /// Verificar acesso a feature
  Future<Map<String, dynamic>> checkFeatureAccess(String featureName) async {
    try {
      return await _subscriptionService.checkFeatureAccess(featureName);
    } catch (e) {
      return {
        'can_use': false,
        'current_usage': 0,
        'remaining_limit': 0,
        'is_unlimited': false,
      };
    }
  }

  /// Verificar se pode usar feature
  Future<bool> canUseFeature(String featureName) async {
    final access = await checkFeatureAccess(featureName);
    return access['can_use'] ?? false;
  }

  /// Obter informações de uso de uma feature
  Future<String> getFeatureUsageInfo(String featureName) async {
    final access = await checkFeatureAccess(featureName);
    
    if (access['is_unlimited'] == true) {
      return 'Uso ilimitado';
    }
    
    final currentUsage = access['current_usage'] ?? 0;
    final remainingLimit = access['remaining_limit'] ?? 0;
    final totalLimit = currentUsage + remainingLimit;
    
    return '$currentUsage de $totalLimit usados';
  }

  /// Verificar se precisa mostrar alerta de limite
  Future<bool> shouldShowLimitAlert(String featureName) async {
    try {
      return await _subscriptionService.isFeatureNearLimit(featureName);
    } catch (e) {
      return false;
    }
  }

  /// Limpar erro
  void clearError() {
    _error = null;
    notifyListeners();
  }

  /// Refrescar todos os dados
  Future<void> refresh() async {
    await Future.wait([
      loadPlans(),
      loadCurrentSubscription(),
    ]);
  }

  void _setLoading(bool loading) {
    _isLoading = loading;
    notifyListeners();
  }

  /// Obter plano recomendado baseado no uso atual
  SubscriptionPlan? getRecommendedPlan() {
    if (_plans.isEmpty || _currentSubscription == null) return null;
    
    // Se já é premium, recomendar anual
    if (isPremium && _currentSubscription!.planSlug == 'premium') {
      return _plans.firstWhere(
        (plan) => plan.slug == 'premium-yearly',
        orElse: () => _plans.first,
      );
    }
    
    // Se é gratuito, recomendar premium
    if (_currentSubscription!.isFree) {
      return _plans.firstWhere(
        (plan) => plan.slug == 'premium',
        orElse: () => _plans.first,
      );
    }
    
    return null;
  }

  /// Verificar se deve mostrar oferta especial
  bool shouldShowSpecialOffer() {
    if (_currentSubscription == null) return false;
    
    // Mostrar para trial expirando
    if (isTrialExpiring) return true;
    
    // Mostrar para usuários gratuitos após uso intenso
    if (_currentSubscription!.isFree) {
      // Aqui você pode implementar lógica baseada em uso
      return true;
    }
    
    return false;
  }

  /// Calcular economia anual
  double getYearlySavings() {
    final monthlyPlan = _plans.where((p) => p.slug == 'premium').firstOrNull;
    final yearlyPlan = _plans.where((p) => p.slug == 'premium-yearly').firstOrNull;
    
    if (monthlyPlan == null || yearlyPlan == null) return 0;
    
    final monthlyYearlyCost = monthlyPlan.price * 12;
    return monthlyYearlyCost - yearlyPlan.price;
  }
}