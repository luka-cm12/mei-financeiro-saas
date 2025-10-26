class Category {
  final int id;
  final int userId;
  final String name;
  final String type; // 'receita' ou 'despesa'
  final String icon;
  final String color;
  final DateTime createdAt;

  Category({
    required this.id,
    required this.userId,
    required this.name,
    required this.type,
    required this.icon,
    required this.color,
    required this.createdAt,
  });

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: int.parse(json['id'].toString()),
      userId: int.parse(json['user_id'].toString()),
      name: json['name'] ?? '',
      type: json['type'] ?? '',
      icon: json['icon'] ?? '📊',
      color: json['color'] ?? '#007BFF',
      createdAt: json['created_at'] != null 
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'name': name,
      'type': type,
      'icon': icon,
      'color': color,
      'created_at': createdAt.toIso8601String(),
    };
  }

  bool get isReceita => type == 'receita';
  bool get isDespesa => type == 'despesa';
}