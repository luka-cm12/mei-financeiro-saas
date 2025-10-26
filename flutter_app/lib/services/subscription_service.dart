import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class SubscriptionPlan {
  final int id;
  final String name;
  final String slug;
  final String description;
  final double price;
  final String currency;
  final String billingPeriod;
  final List<String> features;
  final Map<String, dynamic> limits;
  final String priceFormatted;
  final bool isFree;

  SubscriptionPlan({
    required this.id,
    required this.name,
    required this.slug,
    required this.description,
    required this.price,
    required this.currency,
    required this.billingPeriod,
    required this.features,
    required this.limits,
    required this.priceFormatted,
    required this.isFree,
  });

  factory SubscriptionPlan.fromJson(Map<String, dynamic> json) {
    return SubscriptionPlan(
      id: int.parse(json['id'].toString()),
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      description: json['description'] ?? '',
      price: double.parse(json['price'].toString()),
      currency: json['currency'] ?? 'BRL',
      billingPeriod: json['billing_period'] ?? 'monthly',
      features: List<String>.from(json['features'] ?? []),
      limits: Map<String, dynamic>.from(json['limits'] ?? {}),
      priceFormatted: json['price_formatted'] ?? '',
      isFree: json['is_free'] ?? false,
    );
  }
}

class UserSubscription {
  final int? id;
  final int userId;
  final int planId;
  final String status;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final DateTime? trialEndsAt;
  final String planName;
  final String planSlug;
  final List<String> features;
  final Map<String, dynamic> limits;
  final List<Map<String, dynamic>> usageStats;
  final bool autoRenew;

  UserSubscription({
    this.id,
    required this.userId,
    required this.planId,
    required this.status,
    this.startsAt,
    this.endsAt,
    this.trialEndsAt,
    required this.planName,
    required this.planSlug,
    required this.features,
    required this.limits,
    required this.usageStats,
    required this.autoRenew,
  });

  factory UserSubscription.fromJson(Map<String, dynamic> json) {
    return UserSubscription(
      id: json['id'] != null ? int.parse(json['id'].toString()) : null,
      userId: int.parse(json['user_id'].toString()),
      planId: int.parse(json['plan_id'].toString()),
      status: json['status'] ?? 'free',
      startsAt: json['starts_at'] != null ? DateTime.parse(json['starts_at']) : null,
      endsAt: json['ends_at'] != null ? DateTime.parse(json['ends_at']) : null,
      trialEndsAt: json['trial_ends_at'] != null ? DateTime.parse(json['trial_ends_at']) : null,
      planName: json['plan_name'] ?? '',
      planSlug: json['plan_slug'] ?? '',
      features: List<String>.from(json['features'] ?? []),
      limits: Map<String, dynamic>.from(json['limits'] ?? {}),
      usageStats: List<Map<String, dynamic>>.from(json['usage_stats'] ?? []),
      autoRenew: json['auto_renew'] == 1 || json['auto_renew'] == true,
    );
  }

  bool get isActive => status == 'active' || status == 'trial';
  bool get isTrial => status == 'trial';
  bool get isFree => status == 'free';
  
  int get daysRemaining {
    if (endsAt == null) return 0;
    return endsAt!.difference(DateTime.now()).inDays;
  }

  int get trialDaysRemaining {
    if (trialEndsAt == null) return 0;
    return trialEndsAt!.difference(DateTime.now()).inDays;
  }

  bool get isTrialExpiring => isTrial && trialDaysRemaining <= 3;
}

class SubscriptionService {
  static const String baseUrl = 'http://localhost/mei-financeiro-saas/api';

  Future<String?> _getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<Map<String, String>> _getHeaders() async {
    final token = await _getToken();
    return {
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  /// Listar todos os planos disponíveis
  Future<List<SubscriptionPlan>> getPlans() async {
    try {
      final headers = await _getHeaders();
      final response = await http.get(
        Uri.parse('$baseUrl/subscription.php'),
        headers: headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          return (data['data'] as List)
              .map((plan) => SubscriptionPlan.fromJson(plan))
              .toList();
        }
        throw Exception(data['message'] ?? 'Erro ao carregar planos');
      }
      throw Exception('Erro de conexão: ${response.statusCode}');
    } catch (e) {
      throw Exception('Erro ao carregar planos: $e');
    }
  }

  /// Obter detalhes de um plano específico
  Future<SubscriptionPlan> getPlan(String slug) async {
    try {
      final headers = await _getHeaders();
      final response = await http.get(
        Uri.parse('$baseUrl/subscription.php/plan/$slug'),
        headers: headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          return SubscriptionPlan.fromJson(data['data']);
        }
        throw Exception(data['message'] ?? 'Plano não encontrado');
      }
      throw Exception('Erro de conexão: ${response.statusCode}');
    } catch (e) {
      throw Exception('Erro ao carregar plano: $e');
    }
  }

  /// Obter assinatura atual do usuário
  Future<UserSubscription?> getCurrentSubscription() async {
    try {
      final headers = await _getHeaders();
      final response = await http.get(
        Uri.parse('$baseUrl/subscription.php/current'),
        headers: headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] && data['data'] != null) {
          return UserSubscription.fromJson(data['data']);
        }
      }
      return null;
    } catch (e) {
      throw Exception('Erro ao carregar assinatura: $e');
    }
  }

  /// Criar preferência de pagamento no MercadoPago
  Future<Map<String, dynamic>> createPaymentPreference(String planSlug, {String? successUrl, String? failureUrl}) async {
    try {
      final headers = await _getHeaders();
      final body = json.encode({
        'plan_slug': planSlug,
        'success_url': successUrl,
        'failure_url': failureUrl,
      });

      final response = await http.post(
        Uri.parse('$baseUrl/payment-preference'),
        headers: headers,
        body: body,
      );

      final data = json.decode(response.body);
      
      if (response.statusCode == 201) {
        if (data['success']) {
          return data['data'];
        }
      }
      
      throw Exception(data['message'] ?? 'Erro ao criar pagamento');
    } catch (e) {
      throw Exception('Erro ao criar pagamento: $e');
    }
  }

  /// Criar pagamento PIX
  Future<Map<String, dynamic>> createPixPayment(String planSlug) async {
    try {
      final headers = await _getHeaders();
      final body = json.encode({
        'plan_slug': planSlug,
      });

      final response = await http.post(
        Uri.parse('$baseUrl/payment-pix'),
        headers: headers,
        body: body,
      );

      final data = json.decode(response.body);
      
      if (response.statusCode == 201) {
        if (data['success']) {
          return data['data'];
        }
      }
      
      throw Exception(data['message'] ?? 'Erro ao criar PIX');
    } catch (e) {
      throw Exception('Erro ao criar PIX: $e');
    }
  }

  /// Criar assinatura recorrente com cartão
  Future<Map<String, dynamic>> createSubscription(String planSlug, String cardToken, {String? successUrl}) async {
    try {
      final headers = await _getHeaders();
      final body = json.encode({
        'plan_slug': planSlug,
        'card_token': cardToken,
        'success_url': successUrl,
      });

      final response = await http.post(
        Uri.parse('$baseUrl/subscription'),
        headers: headers,
        body: body,
      );

      final data = json.decode(response.body);
      
      if (response.statusCode == 201) {
        if (data['success']) {
          return data['data'];
        }
      }
      
      throw Exception(data['message'] ?? 'Erro ao criar assinatura');
    } catch (e) {
      throw Exception('Erro ao criar assinatura: $e');
    }
  }

  /// Verificar status do pagamento
  Future<Map<String, dynamic>> checkPaymentStatus(String paymentId) async {
    try {
      final headers = await _getHeaders();

      final response = await http.get(
        Uri.parse('$baseUrl/payment-status/$paymentId'),
        headers: headers,
      );

      final data = json.decode(response.body);
      
      if (response.statusCode == 200) {
        if (data['success']) {
          return data['data'];
        }
      }
      
      throw Exception(data['message'] ?? 'Erro ao verificar pagamento');
    } catch (e) {
      throw Exception('Erro ao verificar pagamento: $e');
    }
  }

  /// Iniciar trial ou assinatura
  Future<Map<String, dynamic>> subscribe(String planSlug, {bool trial = false}) async {
    try {
      final headers = await _getHeaders();
      final body = json.encode({
        'plan_slug': planSlug,
        'trial': trial,
      });

      final response = await http.post(
        Uri.parse('$baseUrl/subscription.php'),
        headers: headers,
        body: body,
      );

      final data = json.decode(response.body);
      
      if (response.statusCode == 201 || response.statusCode == 200) {
        if (data['success']) {
          return data;
        }
      }
      
      throw Exception(data['message'] ?? 'Erro ao processar assinatura');
    } catch (e) {
      throw Exception('Erro ao processar assinatura: $e');
    }
  }

  /// Cancelar assinatura
  Future<bool> cancelSubscription() async {
    try {
      final headers = await _getHeaders();
      final response = await http.put(
        Uri.parse('$baseUrl/subscription.php/cancel'),
        headers: headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return data['success'] ?? false;
      }
      return false;
    } catch (e) {
      throw Exception('Erro ao cancelar assinatura: $e');
    }
  }

  /// Verificar acesso a uma feature
  Future<Map<String, dynamic>> checkFeatureAccess(String featureName) async {
    try {
      final headers = await _getHeaders();
      final response = await http.get(
        Uri.parse('$baseUrl/subscription.php/check/$featureName'),
        headers: headers,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          return data['data'];
        }
      }
      return {
        'can_use': false,
        'current_usage': 0,
        'remaining_limit': 0,
        'is_unlimited': false,
      };
    } catch (e) {
      throw Exception('Erro ao verificar acesso: $e');
    }
  }

  /// Verificar se feature está próxima do limite
  Future<bool> isFeatureNearLimit(String featureName, {double threshold = 0.8}) async {
    try {
      final access = await checkFeatureAccess(featureName);
      
      if (access['is_unlimited'] == true) return false;
      
      final currentUsage = access['current_usage'] ?? 0;
      final remainingLimit = access['remaining_limit'] ?? 0;
      final totalLimit = currentUsage + remainingLimit;
      
      if (totalLimit == 0) return false;
      
      final usagePercentage = currentUsage / totalLimit;
      return usagePercentage >= threshold;
    } catch (e) {
      return false;
    }
  }
}