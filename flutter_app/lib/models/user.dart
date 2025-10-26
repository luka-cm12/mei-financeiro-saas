class User {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? businessName;
  final String? businessType;
  final String? cnpj;
  final String subscriptionStatus;
  final DateTime? subscriptionExpiresAt;
  final DateTime createdAt;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.businessName,
    this.businessType,
    this.cnpj,
    required this.subscriptionStatus,
    this.subscriptionExpiresAt,
    required this.createdAt,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: int.parse(json['id'].toString()),
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'],
      businessName: json['business_name'],
      businessType: json['business_type'],
      cnpj: json['cnpj'],
      subscriptionStatus: json['subscription_status'] ?? 'trial',
      subscriptionExpiresAt: json['subscription_expires_at'] != null
          ? DateTime.parse(json['subscription_expires_at'])
          : null,
      createdAt: json['created_at'] != null 
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'business_name': businessName,
      'business_type': businessType,
      'cnpj': cnpj,
      'subscription_status': subscriptionStatus,
      'subscription_expires_at': subscriptionExpiresAt?.toIso8601String(),
      'created_at': createdAt.toIso8601String(),
    };
  }

  bool get hasActiveSubscription {
    if (subscriptionStatus == 'trial' || subscriptionStatus == 'active') {
      if (subscriptionExpiresAt != null) {
        return DateTime.now().isBefore(subscriptionExpiresAt!);
      }
      return subscriptionStatus == 'trial';
    }
    return false;
  }

  int get daysUntilExpiration {
    if (subscriptionExpiresAt == null) return 0;
    final now = DateTime.now();
    if (subscriptionExpiresAt!.isBefore(now)) return 0;
    return subscriptionExpiresAt!.difference(now).inDays;
  }
}