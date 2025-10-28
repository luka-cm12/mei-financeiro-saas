class Product {
  final int? id;
  final String name;
  final String? description;
  final double price;
  final String unit;
  final String? ncm;
  final String cfop;
  final String icmsOrigin;
  final String icmsTaxSituation;
  final String pisTaxSituation;
  final String cofinsTaxSituation;
  final bool active;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  Product({
    this.id,
    required this.name,
    this.description,
    required this.price,
    required this.unit,
    this.ncm,
    required this.cfop,
    required this.icmsOrigin,
    required this.icmsTaxSituation,
    required this.pisTaxSituation,
    required this.cofinsTaxSituation,
    this.active = true,
    this.createdAt,
    this.updatedAt,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id'] != null ? int.tryParse(json['id'].toString()) : null,
      name: json['name'] ?? '',
      description: json['description'],
      price: double.tryParse(json['price'].toString()) ?? 0.0,
      unit: json['unit'] ?? 'UN',
      ncm: json['ncm'],
      cfop: json['cfop'] ?? '5102',
      icmsOrigin: json['icms_origin'] ?? '0',
      icmsTaxSituation: json['icms_tax_situation'] ?? '102',
      pisTaxSituation: json['pis_tax_situation'] ?? '07',
      cofinsTaxSituation: json['cofins_tax_situation'] ?? '07',
      active: json['active'] == 1 || json['active'] == true,
      createdAt: json['created_at'] != null 
        ? DateTime.tryParse(json['created_at']) 
        : null,
      updatedAt: json['updated_at'] != null 
        ? DateTime.tryParse(json['updated_at']) 
        : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (id != null) 'id': id,
      'name': name,
      'description': description,
      'price': price,
      'unit': unit,
      'ncm': ncm,
      'cfop': cfop,
      'icms_origin': icmsOrigin,
      'icms_tax_situation': icmsTaxSituation,
      'pis_tax_situation': pisTaxSituation,
      'cofins_tax_situation': cofinsTaxSituation,
      'active': active,
    };
  }

  Product copyWith({
    int? id,
    String? name,
    String? description,
    double? price,
    String? unit,
    String? ncm,
    String? cfop,
    String? icmsOrigin,
    String? icmsTaxSituation,
    String? pisTaxSituation,
    String? cofinsTaxSituation,
    bool? active,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return Product(
      id: id ?? this.id,
      name: name ?? this.name,
      description: description ?? this.description,
      price: price ?? this.price,
      unit: unit ?? this.unit,
      ncm: ncm ?? this.ncm,
      cfop: cfop ?? this.cfop,
      icmsOrigin: icmsOrigin ?? this.icmsOrigin,
      icmsTaxSituation: icmsTaxSituation ?? this.icmsTaxSituation,
      pisTaxSituation: pisTaxSituation ?? this.pisTaxSituation,
      cofinsTaxSituation: cofinsTaxSituation ?? this.cofinsTaxSituation,
      active: active ?? this.active,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  String get formattedPrice => 'R\$ ${price.toStringAsFixed(2).replaceAll('.', ',')}';
  
  String get displayName => name.length > 30 ? '${name.substring(0, 30)}...' : name;
}

class NFCeItem {
  final int? productId;
  final String description;
  final double quantity;
  final double unitPrice;
  final double totalPrice;
  final String? ncm;
  final String cfop;
  final String icmsOrigin;
  final String icmsTaxSituation;
  final String pisTaxSituation;
  final String cofinsTaxSituation;

  NFCeItem({
    this.productId,
    required this.description,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    this.ncm,
    required this.cfop,
    required this.icmsOrigin,
    required this.icmsTaxSituation,
    required this.pisTaxSituation,
    required this.cofinsTaxSituation,
  });

  factory NFCeItem.fromProduct(Product product, double quantity) {
    final totalPrice = quantity * product.price;
    
    return NFCeItem(
      productId: product.id,
      description: product.name,
      quantity: quantity,
      unitPrice: product.price,
      totalPrice: totalPrice,
      ncm: product.ncm,
      cfop: product.cfop,
      icmsOrigin: product.icmsOrigin,
      icmsTaxSituation: product.icmsTaxSituation,
      pisTaxSituation: product.pisTaxSituation,
      cofinsTaxSituation: product.cofinsTaxSituation,
    );
  }

  factory NFCeItem.fromJson(Map<String, dynamic> json) {
    return NFCeItem(
      productId: json['product_id'] != null ? int.tryParse(json['product_id'].toString()) : null,
      description: json['description'] ?? '',
      quantity: double.tryParse(json['quantity'].toString()) ?? 0.0,
      unitPrice: double.tryParse(json['unit_price'].toString()) ?? 0.0,
      totalPrice: double.tryParse(json['total_price'].toString()) ?? 0.0,
      ncm: json['ncm'],
      cfop: json['cfop'] ?? '5102',
      icmsOrigin: json['icms_origin'] ?? '0',
      icmsTaxSituation: json['icms_tax_situation'] ?? '102',
      pisTaxSituation: json['pis_tax_situation'] ?? '07',
      cofinsTaxSituation: json['cofins_tax_situation'] ?? '07',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (productId != null) 'product_id': productId,
      'description': description,
      'quantity': quantity,
      'unit_price': unitPrice,
      'total_price': totalPrice,
      'ncm': ncm,
      'cfop': cfop,
      'icms_origin': icmsOrigin,
      'icms_tax_situation': icmsTaxSituation,
      'pis_tax_situation': pisTaxSituation,
      'cofins_tax_situation': cofinsTaxSituation,
    };
  }

  NFCeItem copyWith({
    int? productId,
    String? description,
    double? quantity,
    double? unitPrice,
    double? totalPrice,
    String? ncm,
    String? cfop,
    String? icmsOrigin,
    String? icmsTaxSituation,
    String? pisTaxSituation,
    String? cofinsTaxSituation,
  }) {
    return NFCeItem(
      productId: productId ?? this.productId,
      description: description ?? this.description,
      quantity: quantity ?? this.quantity,
      unitPrice: unitPrice ?? this.unitPrice,
      totalPrice: totalPrice ?? (this.quantity * this.unitPrice),
      ncm: ncm ?? this.ncm,
      cfop: cfop ?? this.cfop,
      icmsOrigin: icmsOrigin ?? this.icmsOrigin,
      icmsTaxSituation: icmsTaxSituation ?? this.icmsTaxSituation,
      pisTaxSituation: pisTaxSituation ?? this.pisTaxSituation,
      cofinsTaxSituation: cofinsTaxSituation ?? this.cofinsTaxSituation,
    );
  }

  String get formattedQuantity => quantity.toStringAsFixed(2).replaceAll('.', ',');
  String get formattedUnitPrice => 'R\$ ${unitPrice.toStringAsFixed(2).replaceAll('.', ',')}';
  String get formattedTotalPrice => 'R\$ ${totalPrice.toStringAsFixed(2).replaceAll('.', ',')}';
}

class NFCe {
  final int? id;
  final int establishmentId;
  final String nfceNumber;
  final String nfceSeries;
  final String nfceKey;
  final String? customerDocument;
  final String? customerName;
  final String? customerEmail;
  final String? customerPhone;
  final double totalProducts;
  final double totalDiscounts;
  final double totalTax;
  final double totalAmount;
  final String paymentMethod;
  final double paymentAmount;
  final double changeAmount;
  final DateTime emissionDate;
  final String status;
  final String? xmlFilePath;
  final String? pdfFilePath;
  final String? protocolNumber;
  final DateTime? authorizationDate;
  final String? cancellationReason;
  final List<NFCeItem> items;
  final int? totalItems;

  NFCe({
    this.id,
    required this.establishmentId,
    required this.nfceNumber,
    required this.nfceSeries,
    required this.nfceKey,
    this.customerDocument,
    this.customerName,
    this.customerEmail,
    this.customerPhone,
    required this.totalProducts,
    this.totalDiscounts = 0.0,
    this.totalTax = 0.0,
    required this.totalAmount,
    required this.paymentMethod,
    required this.paymentAmount,
    this.changeAmount = 0.0,
    required this.emissionDate,
    required this.status,
    this.xmlFilePath,
    this.pdfFilePath,
    this.protocolNumber,
    this.authorizationDate,
    this.cancellationReason,
    required this.items,
    this.totalItems,
  });

  factory NFCe.fromJson(Map<String, dynamic> json) {
    return NFCe(
      id: json['id'] != null ? int.tryParse(json['id'].toString()) : null,
      establishmentId: int.tryParse(json['establishment_id'].toString()) ?? 0,
      nfceNumber: json['nfce_number']?.toString() ?? '',
      nfceSeries: json['nfce_series']?.toString() ?? '',
      nfceKey: json['nfce_key'] ?? '',
      customerDocument: json['customer_document'],
      customerName: json['customer_name'],
      customerEmail: json['customer_email'],
      customerPhone: json['customer_phone'],
      totalProducts: double.tryParse(json['total_products'].toString()) ?? 0.0,
      totalDiscounts: double.tryParse(json['total_discounts'].toString()) ?? 0.0,
      totalTax: double.tryParse(json['total_tax'].toString()) ?? 0.0,
      totalAmount: double.tryParse(json['total_amount'].toString()) ?? 0.0,
      paymentMethod: json['payment_method'] ?? '',
      paymentAmount: double.tryParse(json['payment_amount'].toString()) ?? 0.0,
      changeAmount: double.tryParse(json['change_amount'].toString()) ?? 0.0,
      emissionDate: DateTime.tryParse(json['emission_date'] ?? '') ?? DateTime.now(),
      status: json['status'] ?? '',
      xmlFilePath: json['xml_file_path'],
      pdfFilePath: json['pdf_file_path'],
      protocolNumber: json['protocol_number'],
      authorizationDate: json['authorization_date'] != null 
        ? DateTime.tryParse(json['authorization_date']) 
        : null,
      cancellationReason: json['cancellation_reason'],
      items: json['items'] != null 
        ? (json['items'] as List).map((item) => NFCeItem.fromJson(item)).toList()
        : [],
      totalItems: json['total_items'] != null 
        ? int.tryParse(json['total_items'].toString()) 
        : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (id != null) 'id': id,
      'establishment_id': establishmentId,
      'nfce_number': nfceNumber,
      'nfce_series': nfceSeries,
      'nfce_key': nfceKey,
      'customer_document': customerDocument,
      'customer_name': customerName,
      'customer_email': customerEmail,
      'customer_phone': customerPhone,
      'total_products': totalProducts,
      'total_discounts': totalDiscounts,
      'total_tax': totalTax,
      'total_amount': totalAmount,
      'payment_method': paymentMethod,
      'payment_amount': paymentAmount,
      'change_amount': changeAmount,
      'emission_date': emissionDate.toIso8601String(),
      'status': status,
      'items': items.map((item) => item.toJson()).toList(),
    };
  }

  String get statusDisplay {
    switch (status) {
      case 'pending':
        return 'Pendente';
      case 'generated':
        return 'Gerada';
      case 'authorized':
        return 'Autorizada';
      case 'rejected':
        return 'Rejeitada';
      case 'cancelled':
        return 'Cancelada';
      case 'error':
        return 'Erro';
      default:
        return 'Desconhecido';
    }
  }

  String get paymentMethodDisplay {
    switch (paymentMethod) {
      case 'money':
        return 'Dinheiro';
      case 'card':
        return 'Cartão de Crédito';
      case 'debit':
        return 'Cartão de Débito';
      case 'pix':
        return 'PIX';
      case 'transfer':
        return 'Transferência';
      default:
        return 'Outros';
    }
  }

  String get formattedTotalProducts => 'R\$ ${totalProducts.toStringAsFixed(2).replaceAll('.', ',')}';
  String get formattedTotalAmount => 'R\$ ${totalAmount.toStringAsFixed(2).replaceAll('.', ',')}';
  String get formattedEmissionDate => '${emissionDate.day.toString().padLeft(2, '0')}/${emissionDate.month.toString().padLeft(2, '0')}/${emissionDate.year}';
  String get formattedEmissionDateTime => '${formattedEmissionDate} ${emissionDate.hour.toString().padLeft(2, '0')}:${emissionDate.minute.toString().padLeft(2, '0')}';

  bool get canBeCancelled => status == 'authorized' && 
    authorizationDate != null && 
    DateTime.now().difference(authorizationDate!).inHours < 24;

  bool get hasXML => xmlFilePath != null && xmlFilePath!.isNotEmpty;
  bool get hasPDF => pdfFilePath != null && pdfFilePath!.isNotEmpty;
}

class NFCeStatistics {
  final int totalNfces;
  final int authorizedCount;
  final int cancelledCount;
  final int pendingCount;
  final double totalRevenue;
  final double averageTicket;

  NFCeStatistics({
    required this.totalNfces,
    required this.authorizedCount,
    required this.cancelledCount,
    required this.pendingCount,
    required this.totalRevenue,
    required this.averageTicket,
  });

  factory NFCeStatistics.fromJson(Map<String, dynamic> json) {
    return NFCeStatistics(
      totalNfces: int.tryParse(json['total_nfces'].toString()) ?? 0,
      authorizedCount: int.tryParse(json['authorized_count'].toString()) ?? 0,
      cancelledCount: int.tryParse(json['cancelled_count'].toString()) ?? 0,
      pendingCount: int.tryParse(json['pending_count'].toString()) ?? 0,
      totalRevenue: double.tryParse(json['total_revenue'].toString()) ?? 0.0,
      averageTicket: double.tryParse(json['average_ticket'].toString()) ?? 0.0,
    );
  }

  String get formattedTotalRevenue => 'R\$ ${totalRevenue.toStringAsFixed(2).replaceAll('.', ',')}';
  String get formattedAverageTicket => 'R\$ ${averageTicket.toStringAsFixed(2).replaceAll('.', ',')}';

  double get successRate => totalNfces > 0 ? (authorizedCount / totalNfces) * 100 : 0;
  String get formattedSuccessRate => '${successRate.toStringAsFixed(1)}%';
}