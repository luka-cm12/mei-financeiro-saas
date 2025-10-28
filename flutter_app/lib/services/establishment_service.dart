import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:file_picker/file_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/establishment_model.dart';

class EstablishmentService {
  static const String baseUrl = 'http://localhost/api'; // Ajustar para sua URL

  Future<Map<String, String>> _getHeaders() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('auth_token');
    
    return {
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  /// Obter dados do estabelecimento
  Future<EstablishmentModel?> getEstablishment() async {
    try {
      final headers = await _getHeaders();
      
      final response = await http.get(
        Uri.parse('$baseUrl/establishment'),
        headers: headers,
      );

      final data = json.decode(response.body);
      
      if (response.statusCode == 200 && data['success']) {
        return EstablishmentModel.fromJson(data['data']);
      } else if (response.statusCode == 404) {
        return null; // Estabelecimento não encontrado
      } else {
        throw Exception(data['message'] ?? 'Erro ao carregar estabelecimento');
      }
    } catch (e) {
      throw Exception('Erro ao carregar estabelecimento: $e');
    }
  }

  /// Salvar dados do estabelecimento
  Future<void> saveEstablishment(Map<String, dynamic> establishmentData) async {
    try {
      final headers = await _getHeaders();
      
      final response = await http.post(
        Uri.parse('$baseUrl/establishment'),
        headers: headers,
        body: json.encode(establishmentData),
      );

      final data = json.decode(response.body);
      
      if (response.statusCode == 200 || response.statusCode == 201) {
        if (!data['success']) {
          throw Exception(data['message'] ?? 'Erro ao salvar estabelecimento');
        }
      } else {
        throw Exception(data['message'] ?? 'Erro ao salvar estabelecimento');
      }
    } catch (e) {
      throw Exception('Erro ao salvar estabelecimento: $e');
    }
  }

  /// Upload de certificado digital
  Future<void> uploadCertificate(PlatformFile certificateFile, String password, String type) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');
      
      final uri = Uri.parse('$baseUrl/establishment/upload-certificate');
      final request = http.MultipartRequest('POST', uri);
      
      // Headers
      if (token != null) {
        request.headers['Authorization'] = 'Bearer $token';
      }
      
      // Arquivo
      if (certificateFile.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'certificate',
            certificateFile.bytes!,
            filename: certificateFile.name,
          ),
        );
      } else {
        throw Exception('Arquivo não encontrado');
      }
      
      // Campos
      request.fields['password'] = password;
      request.fields['type'] = type;
      
      final response = await request.send();
      final responseBody = await response.stream.bytesToString();
      final data = json.decode(responseBody);
      
      if (response.statusCode == 200) {
        if (!data['success']) {
          throw Exception(data['message'] ?? 'Erro ao enviar certificado');
        }
      } else {
        throw Exception(data['message'] ?? 'Erro ao enviar certificado');
      }
    } catch (e) {
      throw Exception('Erro ao enviar certificado: $e');
    }
  }

  /// Configurar NFCe
  Future<void> configureNFCe(Map<String, dynamic> nfceConfig) async {
    try {
      final headers = await _getHeaders();
      
      final response = await http.post(
        Uri.parse('$baseUrl/establishment/configure-nfce'),
        headers: headers,
        body: json.encode(nfceConfig),
      );

      final data = json.decode(response.body);
      
      if (response.statusCode == 200) {
        if (!data['success']) {
          throw Exception(data['message'] ?? 'Erro ao configurar NFCe');
        }
      } else {
        throw Exception(data['message'] ?? 'Erro ao configurar NFCe');
      }
    } catch (e) {
      throw Exception('Erro ao configurar NFCe: $e');
    }
  }

  /// Buscar endereço por CEP
  Future<Map<String, dynamic>?> searchCep(String cep) async {
    try {
      final headers = await _getHeaders();
      
      final response = await http.get(
        Uri.parse('$baseUrl/establishment/search-cep/$cep'),
        headers: headers,
      );

      final data = json.decode(response.body);
      
      if (response.statusCode == 200 && data['success']) {
        return data['data'];
      } else if (response.statusCode == 404) {
        return null; // CEP não encontrado
      } else {
        throw Exception(data['message'] ?? 'Erro ao buscar CEP');
      }
    } catch (e) {
      throw Exception('Erro ao buscar CEP: $e');
    }
  }

  /// Validar documento (CPF/CNPJ)
  bool validateDocument(String document, String type) {
    document = document.replaceAll(RegExp(r'[^0-9]'), '');
    
    if (type == 'cpf') {
      return _validateCPF(document);
    } else if (type == 'cnpj') {
      return _validateCNPJ(document);
    }
    
    return false;
  }

  /// Validar CPF
  bool _validateCPF(String cpf) {
    if (cpf.length != 11) return false;
    if (RegExp(r'^(\d)\1{10}$').hasMatch(cpf)) return false;

    int sum = 0;
    for (int i = 0; i < 9; i++) {
      sum += int.parse(cpf[i]) * (10 - i);
    }
    int digit1 = 11 - (sum % 11);
    if (digit1 >= 10) digit1 = 0;

    sum = 0;
    for (int i = 0; i < 10; i++) {
      sum += int.parse(cpf[i]) * (11 - i);
    }
    int digit2 = 11 - (sum % 11);
    if (digit2 >= 10) digit2 = 0;

    return int.parse(cpf[9]) == digit1 && int.parse(cpf[10]) == digit2;
  }

  /// Validar CNPJ
  bool _validateCNPJ(String cnpj) {
    if (cnpj.length != 14) return false;
    if (RegExp(r'^(\d)\1{13}$').hasMatch(cnpj)) return false;

    List<int> weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    List<int> weights2 = [6, 7, 8, 9, 2, 3, 4, 5, 6, 7, 8, 9];

    int sum = 0;
    for (int i = 0; i < 12; i++) {
      sum += int.parse(cnpj[i]) * weights1[i];
    }
    int digit1 = sum % 11 < 2 ? 0 : 11 - (sum % 11);

    sum = 0;
    for (int i = 0; i < 13; i++) {
      sum += int.parse(cnpj[i]) * weights2[i];
    }
    int digit2 = sum % 11 < 2 ? 0 : 11 - (sum % 11);

    return int.parse(cnpj[12]) == digit1 && int.parse(cnpj[13]) == digit2;
  }

  /// Formatar documento (CPF/CNPJ)
  String formatDocument(String document, String type) {
    document = document.replaceAll(RegExp(r'[^0-9]'), '');
    
    if (type == 'cpf' && document.length == 11) {
      return '${document.substring(0, 3)}.${document.substring(3, 6)}.${document.substring(6, 9)}-${document.substring(9)}';
    } else if (type == 'cnpj' && document.length == 14) {
      return '${document.substring(0, 2)}.${document.substring(2, 5)}.${document.substring(5, 8)}/${document.substring(8, 12)}-${document.substring(12)}';
    }
    
    return document;
  }

  /// Formatar CEP
  String formatCep(String cep) {
    cep = cep.replaceAll(RegExp(r'[^0-9]'), '');
    
    if (cep.length == 8) {
      return '${cep.substring(0, 5)}-${cep.substring(5)}';
    }
    
    return cep;
  }

  /// Formatar telefone
  String formatPhone(String phone) {
    phone = phone.replaceAll(RegExp(r'[^0-9]'), '');
    
    if (phone.length == 11) {
      return '(${phone.substring(0, 2)}) ${phone.substring(2, 7)}-${phone.substring(7)}';
    } else if (phone.length == 10) {
      return '(${phone.substring(0, 2)}) ${phone.substring(2, 6)}-${phone.substring(6)}';
    }
    
    return phone;
  }

  /// Estados brasileiros
  static const List<Map<String, String>> brazilianStates = [
    {'code': 'AC', 'name': 'Acre'},
    {'code': 'AL', 'name': 'Alagoas'},
    {'code': 'AP', 'name': 'Amapá'},
    {'code': 'AM', 'name': 'Amazonas'},
    {'code': 'BA', 'name': 'Bahia'},
    {'code': 'CE', 'name': 'Ceará'},
    {'code': 'DF', 'name': 'Distrito Federal'},
    {'code': 'ES', 'name': 'Espírito Santo'},
    {'code': 'GO', 'name': 'Goiás'},
    {'code': 'MA', 'name': 'Maranhão'},
    {'code': 'MT', 'name': 'Mato Grosso'},
    {'code': 'MS', 'name': 'Mato Grosso do Sul'},
    {'code': 'MG', 'name': 'Minas Gerais'},
    {'code': 'PA', 'name': 'Pará'},
    {'code': 'PB', 'name': 'Paraíba'},
    {'code': 'PR', 'name': 'Paraná'},
    {'code': 'PE', 'name': 'Pernambuco'},
    {'code': 'PI', 'name': 'Piauí'},
    {'code': 'RJ', 'name': 'Rio de Janeiro'},
    {'code': 'RN', 'name': 'Rio Grande do Norte'},
    {'code': 'RS', 'name': 'Rio Grande do Sul'},
    {'code': 'RO', 'name': 'Rondônia'},
    {'code': 'RR', 'name': 'Roraima'},
    {'code': 'SC', 'name': 'Santa Catarina'},
    {'code': 'SP', 'name': 'São Paulo'},
    {'code': 'SE', 'name': 'Sergipe'},
    {'code': 'TO', 'name': 'Tocantins'},
  ];

  /// Regimes tributários
  static const List<Map<String, String>> taxRegimes = [
    {'code': 'mei', 'name': 'MEI - Microempreendedor Individual'},
    {'code': 'simples_nacional', 'name': 'Simples Nacional'},
    {'code': 'lucro_presumido', 'name': 'Lucro Presumido'},
    {'code': 'lucro_real', 'name': 'Lucro Real'},
  ];
}