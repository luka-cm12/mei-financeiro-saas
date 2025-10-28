class EstablishmentModel {
  final int? id;
  final String businessName;
  final String? tradeName;
  final String documentType;
  final String document;
  final String? stateRegistration;
  final String? municipalRegistration;
  final String zipCode;
  final String street;
  final String number;
  final String? complement;
  final String neighborhood;
  final String city;
  final String state;
  final String country;
  final String? phone;
  final String? email;
  final String? website;
  final String taxRegime;
  final String? cnaeMain;
  final List<String> cnaesSecondary;
  final bool nfceEnabled;
  final String nfceEnvironment;
  final int nfceSeries;
  final int nfceNextNumber;
  final bool nfceCscConfigured;
  final String? digitalCertificateType;
  final bool certificateUploaded;
  final String? certificateExpiresAt;
  final String? certificateUploadedAt;
  final String fiscalStatus;
  final String createdAt;
  final String updatedAt;

  EstablishmentModel({
    this.id,
    required this.businessName,
    this.tradeName,
    required this.documentType,
    required this.document,
    this.stateRegistration,
    this.municipalRegistration,
    required this.zipCode,
    required this.street,
    required this.number,
    this.complement,
    required this.neighborhood,
    required this.city,
    required this.state,
    required this.country,
    this.phone,
    this.email,
    this.website,
    required this.taxRegime,
    this.cnaeMain,
    required this.cnaesSecondary,
    required this.nfceEnabled,
    required this.nfceEnvironment,
    required this.nfceSeries,
    required this.nfceNextNumber,
    required this.nfceCscConfigured,
    this.digitalCertificateType,
    required this.certificateUploaded,
    this.certificateExpiresAt,
    this.certificateUploadedAt,
    required this.fiscalStatus,
    required this.createdAt,
    required this.updatedAt,
  });

  factory EstablishmentModel.fromJson(Map<String, dynamic> json) {
    return EstablishmentModel(
      id: json['id'],
      businessName: json['business_name'] ?? '',
      tradeName: json['trade_name'],
      documentType: json['document_type'] ?? 'cpf',
      document: json['document'] ?? '',
      stateRegistration: json['state_registration'],
      municipalRegistration: json['municipal_registration'],
      zipCode: json['zip_code'] ?? '',
      street: json['street'] ?? '',
      number: json['number'] ?? '',
      complement: json['complement'],
      neighborhood: json['neighborhood'] ?? '',
      city: json['city'] ?? '',
      state: json['state'] ?? '',
      country: json['country'] ?? 'Brasil',
      phone: json['phone'],
      email: json['email'],
      website: json['website'],
      taxRegime: json['tax_regime'] ?? 'mei',
      cnaeMain: json['cnae_main'],
      cnaesSecondary: List<String>.from(json['cnaes_secondary'] ?? []),
      nfceEnabled: json['nfce_enabled'] ?? false,
      nfceEnvironment: json['nfce_environment'] ?? 'homologation',
      nfceSeries: json['nfce_series'] ?? 1,
      nfceNextNumber: json['nfce_next_number'] ?? 1,
      nfceCscConfigured: json['nfce_csc_configured'] ?? false,
      digitalCertificateType: json['digital_certificate_type'],
      certificateUploaded: json['certificate_uploaded'] ?? false,
      certificateExpiresAt: json['certificate_expires_at'],
      certificateUploadedAt: json['certificate_uploaded_at'],
      fiscalStatus: json['fiscal_status'] ?? 'active',
      createdAt: json['created_at'] ?? '',
      updatedAt: json['updated_at'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'business_name': businessName,
      'trade_name': tradeName,
      'document_type': documentType,
      'document': document,
      'state_registration': stateRegistration,
      'municipal_registration': municipalRegistration,
      'zip_code': zipCode,
      'street': street,
      'number': number,
      'complement': complement,
      'neighborhood': neighborhood,
      'city': city,
      'state': state,
      'country': country,
      'phone': phone,
      'email': email,
      'website': website,
      'tax_regime': taxRegime,
      'cnae_main': cnaeMain,
      'cnaes_secondary': cnaesSecondary,
      'nfce_enabled': nfceEnabled,
      'nfce_environment': nfceEnvironment,
      'nfce_series': nfceSeries,
      'nfce_next_number': nfceNextNumber,
      'nfce_csc_configured': nfceCscConfigured,
      'digital_certificate_type': digitalCertificateType,
      'certificate_uploaded': certificateUploaded,
      'certificate_expires_at': certificateExpiresAt,
      'certificate_uploaded_at': certificateUploadedAt,
      'fiscal_status': fiscalStatus,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  // Getters utilitários
  String get formattedDocument {
    if (documentType == 'cpf') {
      return document.replaceAllMapped(
        RegExp(r'(\d{3})(\d{3})(\d{3})(\d{2})'),
        (match) => '${match[1]}.${match[2]}.${match[3]}-${match[4]}',
      );
    } else {
      return document.replaceAllMapped(
        RegExp(r'(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})'),
        (match) => '${match[1]}.${match[2]}.${match[3]}/${match[4]}-${match[5]}',
      );
    }
  }

  String get formattedZipCode {
    return zipCode.replaceAllMapped(
      RegExp(r'(\d{5})(\d{3})'),
      (match) => '${match[1]}-${match[2]}',
    );
  }

  String get fullAddress {
    final parts = <String>[
      street,
      number,
      if (complement?.isNotEmpty == true) complement!,
      neighborhood,
      city,
      state,
      formattedZipCode,
    ];
    return parts.join(', ');
  }

  String get taxRegimeDisplay {
    switch (taxRegime) {
      case 'mei':
        return 'MEI';
      case 'simples_nacional':
        return 'Simples Nacional';
      case 'lucro_presumido':
        return 'Lucro Presumido';
      case 'lucro_real':
        return 'Lucro Real';
      default:
        return taxRegime.toUpperCase();
    }
  }

  bool get isNFCeConfigured {
    return nfceEnabled && 
           certificateUploaded && 
           nfceCscConfigured;
  }

  bool get certificateExpiringSoon {
    if (certificateExpiresAt == null) return false;
    
    try {
      final expirationDate = DateTime.parse(certificateExpiresAt!);
      final now = DateTime.now();
      final daysUntilExpiration = expirationDate.difference(now).inDays;
      
      return daysUntilExpiration <= 30; // Expira em 30 dias ou menos
    } catch (e) {
      return false;
    }
  }

  EstablishmentModel copyWith({
    int? id,
    String? businessName,
    String? tradeName,
    String? documentType,
    String? document,
    String? stateRegistration,
    String? municipalRegistration,
    String? zipCode,
    String? street,
    String? number,
    String? complement,
    String? neighborhood,
    String? city,
    String? state,
    String? country,
    String? phone,
    String? email,
    String? website,
    String? taxRegime,
    String? cnaeMain,
    List<String>? cnaesSecondary,
    bool? nfceEnabled,
    String? nfceEnvironment,
    int? nfceSeries,
    int? nfceNextNumber,
    bool? nfceCscConfigured,
    String? digitalCertificateType,
    bool? certificateUploaded,
    String? certificateExpiresAt,
    String? certificateUploadedAt,
    String? fiscalStatus,
    String? createdAt,
    String? updatedAt,
  }) {
    return EstablishmentModel(
      id: id ?? this.id,
      businessName: businessName ?? this.businessName,
      tradeName: tradeName ?? this.tradeName,
      documentType: documentType ?? this.documentType,
      document: document ?? this.document,
      stateRegistration: stateRegistration ?? this.stateRegistration,
      municipalRegistration: municipalRegistration ?? this.municipalRegistration,
      zipCode: zipCode ?? this.zipCode,
      street: street ?? this.street,
      number: number ?? this.number,
      complement: complement ?? this.complement,
      neighborhood: neighborhood ?? this.neighborhood,
      city: city ?? this.city,
      state: state ?? this.state,
      country: country ?? this.country,
      phone: phone ?? this.phone,
      email: email ?? this.email,
      website: website ?? this.website,
      taxRegime: taxRegime ?? this.taxRegime,
      cnaeMain: cnaeMain ?? this.cnaeMain,
      cnaesSecondary: cnaesSecondary ?? this.cnaesSecondary,
      nfceEnabled: nfceEnabled ?? this.nfceEnabled,
      nfceEnvironment: nfceEnvironment ?? this.nfceEnvironment,
      nfceSeries: nfceSeries ?? this.nfceSeries,
      nfceNextNumber: nfceNextNumber ?? this.nfceNextNumber,
      nfceCscConfigured: nfceCscConfigured ?? this.nfceCscConfigured,
      digitalCertificateType: digitalCertificateType ?? this.digitalCertificateType,
      certificateUploaded: certificateUploaded ?? this.certificateUploaded,
      certificateExpiresAt: certificateExpiresAt ?? this.certificateExpiresAt,
      certificateUploadedAt: certificateUploadedAt ?? this.certificateUploadedAt,
      fiscalStatus: fiscalStatus ?? this.fiscalStatus,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }
}