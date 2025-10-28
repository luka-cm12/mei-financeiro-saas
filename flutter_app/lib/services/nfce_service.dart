import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/nfce_model.dart';

class NFCeService {
  static const String baseUrl = 'http://localhost/mei-financeiro-saas/api';

  // Produtos
  Future<List<Product>> getProducts({String? search}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      String url = '$baseUrl/products';
      if (search != null && search.isNotEmpty) {
        url += '?search=${Uri.encodeComponent(search)}';
      }

      final response = await http.get(
        Uri.parse(url),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          return (data['data'] as List)
              .map((item) => Product.fromJson(item))
              .toList();
        } else {
          throw Exception(data['message'] ?? 'Erro ao buscar produtos');
        }
      } else {
        throw Exception('Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao buscar produtos: $e');
    }
  }

  Future<Product> getProduct(int productId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final response = await http.get(
        Uri.parse('$baseUrl/products/$productId'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          return Product.fromJson(data['data']);
        } else {
          throw Exception(data['message'] ?? 'Erro ao buscar produto');
        }
      } else {
        throw Exception('Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao buscar produto: $e');
    }
  }

  Future<Product> saveProduct(Product product) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final bool isUpdate = product.id != null;
      final String url = isUpdate 
          ? '$baseUrl/products/${product.id}'
          : '$baseUrl/products';

      final response = await http.request(
        isUpdate ? 'PUT' : 'POST',
        Uri.parse(url),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: json.encode(product.toJson()),
      );

      if (response.statusCode == 200 || response.statusCode == 201) {
        final data = json.decode(response.body);
        if (data['success']) {
          return Product.fromJson(data['data']);
        } else {
          throw Exception(data['message'] ?? 'Erro ao salvar produto');
        }
      } else {
        throw Exception('Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao salvar produto: $e');
    }
  }

  Future<void> deleteProduct(int productId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final response = await http.delete(
        Uri.parse('$baseUrl/products/$productId'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (!data['success']) {
          throw Exception(data['message'] ?? 'Erro ao remover produto');
        }
      } else {
        throw Exception('Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao remover produto: $e');
    }
  }

  Future<List<Product>> getMostSoldProducts({int limit = 10}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final response = await http.get(
        Uri.parse('$baseUrl/products/analytics/most-sold?limit=$limit'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          return (data['data'] as List)
              .map((item) => Product.fromJson(item))
              .toList();
        } else {
          throw Exception(data['message'] ?? 'Erro ao buscar produtos mais vendidos');
        }
      } else {
        throw Exception('Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao buscar produtos mais vendidos: $e');
    }
  }

  // NFCe
  Future<List<NFCe>> getNFCes({
    String? status,
    String? dateFrom,
    String? dateTo,
    String? customerDocument,
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      String url = '$baseUrl/nfce';
      List<String> queryParams = [];

      if (status != null && status.isNotEmpty) {
        queryParams.add('status=${Uri.encodeComponent(status)}');
      }
      if (dateFrom != null && dateFrom.isNotEmpty) {
        queryParams.add('date_from=${Uri.encodeComponent(dateFrom)}');
      }
      if (dateTo != null && dateTo.isNotEmpty) {
        queryParams.add('date_to=${Uri.encodeComponent(dateTo)}');
      }
      if (customerDocument != null && customerDocument.isNotEmpty) {
        queryParams.add('customer_document=${Uri.encodeComponent(customerDocument)}');
      }

      if (queryParams.isNotEmpty) {
        url += '?' + queryParams.join('&');
      }

      final response = await http.get(
        Uri.parse(url),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          return (data['data'] as List)
              .map((item) => NFCe.fromJson(item))
              .toList();
        } else {
          throw Exception(data['message'] ?? 'Erro ao buscar NFCes');
        }
      } else {
        throw Exception('Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao buscar NFCes: $e');
    }
  }

  Future<NFCe> getNFCe(int nfceId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final response = await http.get(
        Uri.parse('$baseUrl/nfce/$nfceId'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          return NFCe.fromJson(data['data']);
        } else {
          throw Exception(data['message'] ?? 'Erro ao buscar NFCe');
        }
      } else {
        throw Exception('Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao buscar NFCe: $e');
    }
  }

  Future<NFCe> emitNFCe({
    required List<NFCeItem> items,
    String? customerDocument,
    String? customerName,
    String? customerEmail,
    String? customerPhone,
    double totalDiscounts = 0.0,
    required String paymentMethod,
    required double paymentAmount,
    double changeAmount = 0.0,
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final requestBody = {
        'items': items.map((item) => item.toJson()).toList(),
        'customer_document': customerDocument,
        'customer_name': customerName,
        'customer_email': customerEmail,
        'customer_phone': customerPhone,
        'total_discounts': totalDiscounts,
        'payment_method': paymentMethod,
        'payment_amount': paymentAmount,
        'change_amount': changeAmount,
      };

      final response = await http.post(
        Uri.parse('$baseUrl/nfce/emit'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: json.encode(requestBody),
      );

      if (response.statusCode == 201) {
        final data = json.decode(response.body);
        if (data['success']) {
          return NFCe.fromJson(data['data']);
        } else {
          throw Exception(data['message'] ?? 'Erro ao emitir NFCe');
        }
      } else {
        final data = json.decode(response.body);
        throw Exception(data['message'] ?? 'Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao emitir NFCe: $e');
    }
  }

  Future<void> cancelNFCe(int nfceId, String reason) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final response = await http.post(
        Uri.parse('$baseUrl/nfce/$nfceId/cancel'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: json.encode({'reason': reason}),
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (!data['success']) {
          throw Exception(data['message'] ?? 'Erro ao cancelar NFCe');
        }
      } else {
        final data = json.decode(response.body);
        throw Exception(data['message'] ?? 'Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao cancelar NFCe: $e');
    }
  }

  Future<NFCeStatistics> getStatistics({String period = 'month'}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final response = await http.get(
        Uri.parse('$baseUrl/nfce/statistics?period=$period'),
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success']) {
          return NFCeStatistics.fromJson(data['data']);
        } else {
          throw Exception(data['message'] ?? 'Erro ao buscar estatísticas');
        }
      } else {
        throw Exception('Erro na requisição: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao buscar estatísticas: $e');
    }
  }

  Future<File> downloadXML(int nfceId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final response = await http.get(
        Uri.parse('$baseUrl/nfce/$nfceId/xml'),
        headers: {
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        // Criar arquivo temporário
        final tempDir = Directory.systemTemp;
        final file = File('${tempDir.path}/nfce_$nfceId.xml');
        
        await file.writeAsBytes(response.bodyBytes);
        return file;
      } else {
        throw Exception('Erro ao baixar XML: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao baixar XML: $e');
    }
  }

  Future<File> downloadPDF(int nfceId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token == null) {
        throw Exception('Token não encontrado');
      }

      final response = await http.get(
        Uri.parse('$baseUrl/nfce/$nfceId/pdf'),
        headers: {
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        // Criar arquivo temporário
        final tempDir = Directory.systemTemp;
        final file = File('${tempDir.path}/nfce_$nfceId.pdf');
        
        await file.writeAsBytes(response.bodyBytes);
        return file;
      } else {
        throw Exception('Erro ao baixar PDF: ${response.statusCode}');
      }
    } catch (e) {
      throw Exception('Erro ao baixar PDF: $e');
    }
  }

  // Utilitários
  Map<String, String> getPaymentMethods() {
    return {
      'money': 'Dinheiro',
      'card': 'Cartão de Crédito',
      'debit': 'Cartão de Débito',
      'pix': 'PIX',
      'transfer': 'Transferência Bancária',
    };
  }

  Map<String, String> getNFCeStatusOptions() {
    return {
      'all': 'Todos',
      'pending': 'Pendente',
      'generated': 'Gerada',
      'authorized': 'Autorizada',
      'rejected': 'Rejeitada',
      'cancelled': 'Cancelada',
      'error': 'Erro',
    };
  }

  Map<String, String> getUnitsOptions() {
    return {
      'UN': 'Unidade',
      'PC': 'Peça',
      'KG': 'Quilograma',
      'M': 'Metro',
      'M2': 'Metro Quadrado',
      'M3': 'Metro Cúbico',
      'L': 'Litro',
      'HR': 'Hora',
      'SV': 'Serviço',
    };
  }

  Map<String, String> getCFOPOptions() {
    return {
      '5102': '5102 - Venda de mercadoria adquirida ou recebida de terceiros',
      '5103': '5103 - Venda de produção do estabelecimento',
      '5104': '5104 - Venda de mercadoria adquirida ou recebida de terceiros (substituição tributária)',
      '5405': '5405 - Venda de mercadoria adquirida ou recebida de terceiros (ICMS ST)',
      '5933': '5933 - Prestação de serviço sujeita ao ICMS',
    };
  }

  String formatCurrency(double value) {
    return 'R\$ ${value.toStringAsFixed(2).replaceAll('.', ',')}';
  }

  String formatDocument(String document) {
    document = document.replaceAll(RegExp(r'[^\d]'), '');
    
    if (document.length == 11) {
      // CPF
      return '${document.substring(0, 3)}.${document.substring(3, 6)}.${document.substring(6, 9)}-${document.substring(9)}';
    } else if (document.length == 14) {
      // CNPJ
      return '${document.substring(0, 2)}.${document.substring(2, 5)}.${document.substring(5, 8)}/${document.substring(8, 12)}-${document.substring(12)}';
    }
    
    return document;
  }

  bool isValidCPF(String cpf) {
    cpf = cpf.replaceAll(RegExp(r'[^\d]'), '');
    
    if (cpf.length != 11) return false;
    if (RegExp(r'^(\d)\1*$').hasMatch(cpf)) return false;
    
    int sum = 0;
    for (int i = 0; i < 9; i++) {
      sum += int.parse(cpf[i]) * (10 - i);
    }
    int remainder = sum % 11;
    int firstDigit = remainder < 2 ? 0 : 11 - remainder;
    
    if (int.parse(cpf[9]) != firstDigit) return false;
    
    sum = 0;
    for (int i = 0; i < 10; i++) {
      sum += int.parse(cpf[i]) * (11 - i);
    }
    remainder = sum % 11;
    int secondDigit = remainder < 2 ? 0 : 11 - remainder;
    
    return int.parse(cpf[10]) == secondDigit;
  }

  bool isValidCNPJ(String cnpj) {
    cnpj = cnpj.replaceAll(RegExp(r'[^\d]'), '');
    
    if (cnpj.length != 14) return false;
    if (RegExp(r'^(\d)\1*$').hasMatch(cnpj)) return false;
    
    List<int> weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    List<int> weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    
    int sum = 0;
    for (int i = 0; i < 12; i++) {
      sum += int.parse(cnpj[i]) * weights1[i];
    }
    int remainder = sum % 11;
    int firstDigit = remainder < 2 ? 0 : 11 - remainder;
    
    if (int.parse(cnpj[12]) != firstDigit) return false;
    
    sum = 0;
    for (int i = 0; i < 13; i++) {
      sum += int.parse(cnpj[i]) * weights2[i];
    }
    remainder = sum % 11;
    int secondDigit = remainder < 2 ? 0 : 11 - remainder;
    
    return int.parse(cnpj[13]) == secondDigit;
  }
}