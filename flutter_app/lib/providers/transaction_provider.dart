import 'package:flutter/foundation.dart';
import '../models/transaction.dart';
import '../models/category.dart' as AppCategory;
import '../services/api_service.dart';

class TransactionProvider with ChangeNotifier {
  List<Transaction> _transactions = [];
  List<AppCategory.Category> _categories = [];
  bool _isLoading = false;
  String? _errorMessage;
  
  // Filtros
  String? _selectedType;
  int? _selectedCategoryId;
  DateTime? _startDate;
  DateTime? _endDate;
  
  // Paginação
  int _currentPage = 1;
  int _totalPages = 1;
  bool _hasMore = false;

  // Getters
  List<Transaction> get transactions => _transactions;
  List<AppCategory.Category> get categories => _categories;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  String? get selectedType => _selectedType;
  int? get selectedCategoryId => _selectedCategoryId;
  DateTime? get startDate => _startDate;
  DateTime? get endDate => _endDate;
  int get currentPage => _currentPage;
  bool get hasMore => _hasMore;

  // Filtros por tipo
  List<AppCategory.Category> get receitaCategories => 
      _categories.where((c) => c.type == 'receita').toList();
  
  List<AppCategory.Category> get despesaCategories => 
      _categories.where((c) => c.type == 'despesa').toList();

  // Estatísticas rápidas
  double get totalReceitas => _transactions
      .where((t) => t.type == 'receita')
      .fold(0.0, (sum, t) => sum + t.amount);

  double get totalDespesas => _transactions
      .where((t) => t.type == 'despesa')
      .fold(0.0, (sum, t) => sum + t.amount);

  double get lucroTotal => totalReceitas - totalDespesas;

  // Carregar categorias
  Future<void> loadCategories() async {
    try {
      final response = await ApiService.get('categories');
      
      if (response.success && response.data['categories'] != null) {
        _categories = (response.data['categories'] as List)
            .map((json) => AppCategory.Category.fromJson(json))
            .toList();
        notifyListeners();
      } else {
        _setError(response.message);
      }
    } catch (e) {
      _setError('Erro ao carregar categorias: $e');
    }
  }

  // Carregar transações
  Future<void> loadTransactions({bool refresh = false}) async {
    if (refresh) {
      _currentPage = 1;
      _transactions.clear();
    }

    _setLoading(true);
    _clearError();

    try {
      final queryParams = <String, String>{
        'page': _currentPage.toString(),
        'limit': '20',
      };

      if (_selectedType != null) {
        queryParams['type'] = _selectedType!;
      }
      
      if (_selectedCategoryId != null) {
        queryParams['category_id'] = _selectedCategoryId.toString();
      }
      
      if (_startDate != null) {
        queryParams['start_date'] = _startDate!.toIso8601String().split('T')[0];
      }
      
      if (_endDate != null) {
        queryParams['end_date'] = _endDate!.toIso8601String().split('T')[0];
      }

      final queryString = queryParams.entries
          .map((e) => '${e.key}=${Uri.encodeComponent(e.value)}')
          .join('&');

      final response = await ApiService.get('transactions?$queryString');
      
      if (response.success) {
        final List<Transaction> newTransactions = 
            (response.data['transactions'] as List)
                .map((json) => Transaction.fromJson(json))
                .toList();

        if (refresh) {
          _transactions = newTransactions;
        } else {
          _transactions.addAll(newTransactions);
        }

        final pagination = response.data['pagination'];
        _currentPage = pagination['current_page'];
        _totalPages = pagination['total_pages'];
        _hasMore = _currentPage < _totalPages;

        notifyListeners();
      } else {
        _setError(response.message);
      }
    } catch (e) {
      _setError('Erro ao carregar transações: $e');
    } finally {
      _setLoading(false);
    }
  }

  // Carregar mais transações (paginação)
  Future<void> loadMoreTransactions() async {
    if (!_hasMore || _isLoading) return;

    _currentPage++;
    await loadTransactions();
  }

  // Criar transação
  Future<bool> createTransaction(Map<String, dynamic> data) async {
    _setLoading(true);
    _clearError();

    try {
      final response = await ApiService.post('transactions', data);
      
      if (response.success) {
        // Recarregar transações
        await loadTransactions(refresh: true);
        return true;
      } else {
        _setError(response.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao criar transação: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  // Atualizar transação
  Future<bool> updateTransaction(int id, Map<String, dynamic> data) async {
    _setLoading(true);
    _clearError();

    try {
      final response = await ApiService.put('transactions/$id', data);
      
      if (response.success) {
        // Recarregar transações
        await loadTransactions(refresh: true);
        return true;
      } else {
        _setError(response.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao atualizar transação: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  // Deletar transação
  Future<bool> deleteTransaction(int id) async {
    _setLoading(true);
    _clearError();

    try {
      final response = await ApiService.delete('transactions/$id');
      
      if (response.success) {
        // Remover da lista local
        _transactions.removeWhere((t) => t.id == id);
        notifyListeners();
        return true;
      } else {
        _setError(response.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao deletar transação: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  // Criar categoria
  Future<bool> createCategory(Map<String, dynamic> data) async {
    _setLoading(true);
    _clearError();

    try {
      final response = await ApiService.post('categories', data);
      
      if (response.success) {
        // Recarregar categorias
        await loadCategories();
        return true;
      } else {
        _setError(response.message);
        return false;
      }
    } catch (e) {
      _setError('Erro ao criar categoria: $e');
      return false;
    } finally {
      _setLoading(false);
    }
  }

  // Obter relatório mensal
  Future<Map<String, dynamic>?> getMonthlyReport({
    required int year,
    required int month,
  }) async {
    _setLoading(true);
    _clearError();

    try {
      final response = await ApiService.get('reports/monthly?year=$year&month=$month');
      
      if (response.success) {
        return response.data;
      } else {
        _setError(response.message);
        return null;
      }
    } catch (e) {
      _setError('Erro ao carregar relatório: $e');
      return null;
    } finally {
      _setLoading(false);
    }
  }

  // Aplicar filtros
  void setFilters({
    String? type,
    int? categoryId,
    DateTime? startDate,
    DateTime? endDate,
  }) {
    _selectedType = type;
    _selectedCategoryId = categoryId;
    _startDate = startDate;
    _endDate = endDate;
    
    // Recarregar com filtros
    loadTransactions(refresh: true);
  }

  // Limpar filtros
  void clearFilters() {
    _selectedType = null;
    _selectedCategoryId = null;
    _startDate = null;
    _endDate = null;
    
    loadTransactions(refresh: true);
  }

  // Métodos auxiliares
  void _setLoading(bool loading) {
    _isLoading = loading;
    notifyListeners();
  }

  void _setError(String error) {
    _errorMessage = error;
    _isLoading = false;
    notifyListeners();
  }

  void _clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  void clearError() => _clearError();

  AppCategory.Category? getCategoryById(int id) {
    try {
      return _categories.firstWhere((c) => c.id == id);
    } catch (e) {
      return null;
    }
  }
}