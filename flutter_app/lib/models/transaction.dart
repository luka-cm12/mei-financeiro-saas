class Transaction {
  final int id;
  final int userId;
  final int? categoryId;
  final String type; // 'receita' ou 'despesa'
  final double amount;
  final String description;
  final DateTime transactionDate;
  final String? paymentMethod;
  final String? bankReference;
  final bool isRecurring;
  final String? recurringFrequency;
  final List<String> tags;
  final DateTime createdAt;
  final DateTime updatedAt;
  
  // Dados da categoria (se houver)
  final String? categoryName;
  final String? categoryIcon;
  final String? categoryColor;

  Transaction({
    required this.id,
    required this.userId,
    this.categoryId,
    required this.type,
    required this.amount,
    required this.description,
    required this.transactionDate,
    this.paymentMethod,
    this.bankReference,
    this.isRecurring = false,
    this.recurringFrequency,
    this.tags = const [],
    required this.createdAt,
    required this.updatedAt,
    this.categoryName,
    this.categoryIcon,
    this.categoryColor,
  });

  factory Transaction.fromJson(Map<String, dynamic> json) {
    return Transaction(
      id: json['id'],
      userId: json['user_id'],
      categoryId: json['category_id'],
      type: json['type'],
      amount: double.parse(json['amount'].toString()),
      description: json['description'],
      transactionDate: DateTime.parse(json['transaction_date']),
      paymentMethod: json['payment_method'],
      bankReference: json['bank_reference'],
      isRecurring: json['is_recurring'] == 1 || json['is_recurring'] == true,
      recurringFrequency: json['recurring_frequency'],
      tags: json['tags'] != null 
          ? List<String>.from(json['tags'] is String 
              ? [] 
              : json['tags'])
          : [],
      createdAt: DateTime.parse(json['created_at']),
      updatedAt: DateTime.parse(json['updated_at']),
      categoryName: json['category_name'],
      categoryIcon: json['category_icon'],
      categoryColor: json['category_color'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'category_id': categoryId,
      'type': type,
      'amount': amount,
      'description': description,
      'transaction_date': transactionDate.toIso8601String().split('T')[0],
      'payment_method': paymentMethod,
      'bank_reference': bankReference,
      'is_recurring': isRecurring,
      'recurring_frequency': recurringFrequency,
      'tags': tags,
    };
  }

  bool get isReceita => type == 'receita';
  bool get isDespesa => type == 'despesa';

  String get formattedAmount {
    return 'R\$ ${amount.toStringAsFixed(2).replaceAll('.', ',')}';
  }

  String get formattedDate {
    final day = transactionDate.day.toString().padLeft(2, '0');
    final month = transactionDate.month.toString().padLeft(2, '0');
    final year = transactionDate.year;
    return '$day/$month/$year';
  }

  Transaction copyWith({
    int? id,
    int? userId,
    int? categoryId,
    String? type,
    double? amount,
    String? description,
    DateTime? transactionDate,
    String? paymentMethod,
    String? bankReference,
    bool? isRecurring,
    String? recurringFrequency,
    List<String>? tags,
    DateTime? createdAt,
    DateTime? updatedAt,
    String? categoryName,
    String? categoryIcon,
    String? categoryColor,
  }) {
    return Transaction(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      categoryId: categoryId ?? this.categoryId,
      type: type ?? this.type,
      amount: amount ?? this.amount,
      description: description ?? this.description,
      transactionDate: transactionDate ?? this.transactionDate,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      bankReference: bankReference ?? this.bankReference,
      isRecurring: isRecurring ?? this.isRecurring,
      recurringFrequency: recurringFrequency ?? this.recurringFrequency,
      tags: tags ?? this.tags,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      categoryName: categoryName ?? this.categoryName,
      categoryIcon: categoryIcon ?? this.categoryIcon,
      categoryColor: categoryColor ?? this.categoryColor,
    );
  }
}