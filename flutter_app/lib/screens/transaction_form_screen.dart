import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../providers/transaction_provider.dart';
import '../models/category.dart' as AppCategory;

class TransactionFormScreen extends StatefulWidget {
  const TransactionFormScreen({super.key});

  @override
  State<TransactionFormScreen> createState() => _TransactionFormScreenState();
}

class _TransactionFormScreenState extends State<TransactionFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _descriptionController = TextEditingController();
  
  String _selectedType = 'receita';
  AppCategory.Category? _selectedCategory;
  DateTime _selectedDate = DateTime.now();
  String? _paymentMethod;
  
  bool _isEditing = false;
  int? _editingTransactionId;
  
  final List<String> _paymentMethods = [
    'Dinheiro',
    'PIX',
    'Cartão de Débito',
    'Cartão de Crédito',
    'Transferência Bancária',
    'Cheque',
    'Outros',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _checkForEditMode();
    });
  }

  void _checkForEditMode() {
    final args = ModalRoute.of(context)?.settings.arguments as Map<String, dynamic>?;
    if (args != null && args['transactionId'] != null) {
      _isEditing = true;
      _editingTransactionId = args['transactionId'];
      _loadTransactionForEdit();
    }
  }

  void _loadTransactionForEdit() {
    final transactionProvider = Provider.of<TransactionProvider>(context, listen: false);
    final transaction = transactionProvider.transactions
        .where((t) => t.id == _editingTransactionId)
        .firstOrNull;
    
    if (transaction != null) {
      setState(() {
        _selectedType = transaction.type;
        _amountController.text = transaction.amount.toStringAsFixed(2).replaceAll('.', ',');
        _descriptionController.text = transaction.description;
        _selectedDate = transaction.transactionDate;
        _paymentMethod = transaction.paymentMethod;
        
        // Encontrar categoria selecionada
        if (transaction.categoryId != null) {
          _selectedCategory = transactionProvider.getCategoryById(transaction.categoryId!);
        }
      });
    }
  }

  @override
  void dispose() {
    _amountController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _saveTransaction() async {
    if (!_formKey.currentState!.validate()) return;

    final transactionProvider = Provider.of<TransactionProvider>(context, listen: false);
    
    // Converter valor
    final amountText = _amountController.text.replaceAll(',', '.');
    final amount = double.tryParse(amountText);
    
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Informe um valor válido'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final data = {
      'type': _selectedType,
      'amount': amount,
      'description': _descriptionController.text.trim(),
      'transaction_date': DateFormat('yyyy-MM-dd').format(_selectedDate),
      'category_id': _selectedCategory?.id,
      'payment_method': _paymentMethod,
    };

    bool success;
    if (_isEditing) {
      success = await transactionProvider.updateTransaction(_editingTransactionId!, data);
    } else {
      success = await transactionProvider.createTransaction(data);
    }

    if (mounted) {
      if (success) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_isEditing ? 'Transação atualizada!' : 'Transação criada!'),
            backgroundColor: Colors.green,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(transactionProvider.errorMessage ?? 'Erro ao salvar'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _selectDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
    );
    
    if (picked != null) {
      setState(() {
        _selectedDate = picked;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_isEditing ? 'Editar Transação' : 'Nova Transação'),
        actions: [
          Consumer<TransactionProvider>(
            builder: (context, transactionProvider, child) {
              return TextButton(
                onPressed: transactionProvider.isLoading ? null : _saveTransaction,
                child: transactionProvider.isLoading
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Salvar'),
              );
            },
          ),
        ],
      ),
      body: Consumer<TransactionProvider>(
        builder: (context, transactionProvider, child) {
          return Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Seletor de tipo
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Tipo de Transação',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: RadioListTile<String>(
                                title: const Text('Receita'),
                                subtitle: const Text('Entrada de dinheiro'),
                                value: 'receita',
                                groupValue: _selectedType,
                                onChanged: (value) {
                                  setState(() {
                                    _selectedType = value!;
                                    _selectedCategory = null; // Reset category
                                  });
                                },
                                activeColor: Colors.green,
                              ),
                            ),
                            Expanded(
                              child: RadioListTile<String>(
                                title: const Text('Despesa'),
                                subtitle: const Text('Saída de dinheiro'),
                                value: 'despesa',
                                groupValue: _selectedType,
                                onChanged: (value) {
                                  setState(() {
                                    _selectedType = value!;
                                    _selectedCategory = null; // Reset category
                                  });
                                },
                                activeColor: Colors.red,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                
                const SizedBox(height: 16),
                
                // Valor
                TextFormField(
                  controller: _amountController,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(
                    labelText: 'Valor *',
                    prefixIcon: Icon(Icons.attach_money),
                    prefixText: 'R\$ ',
                    hintText: '0,00',
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Informe o valor';
                    }
                    final amount = double.tryParse(value.replaceAll(',', '.'));
                    if (amount == null || amount <= 0) {
                      return 'Informe um valor válido';
                    }
                    return null;
                  },
                ),
                
                const SizedBox(height: 16),
                
                // Descrição
                TextFormField(
                  controller: _descriptionController,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Descrição *',
                    prefixIcon: Icon(Icons.description),
                    hintText: 'Ex: Venda de produto, Pagamento de fornecedor...',
                  ),
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'Informe uma descrição';
                    }
                    return null;
                  },
                ),
                
                const SizedBox(height: 16),
                
                // Categoria
                DropdownButtonFormField<AppCategory.Category>(
                  value: _selectedCategory,
                  decoration: const InputDecoration(
                    labelText: 'Categoria',
                    prefixIcon: Icon(Icons.category),
                  ),
                  items: (_selectedType == 'receita' 
                          ? transactionProvider.receitaCategories
                          : transactionProvider.despesaCategories)
                      .map((category) => DropdownMenuItem<AppCategory.Category>(
                            value: category,
                            child: Text(category.name),
                          ))
                      .toList(),
                  onChanged: (category) {
                    setState(() {
                      _selectedCategory = category;
                    });
                  },
                ),
                
                const SizedBox(height: 16),
                
                // Data
                InkWell(
                  onTap: _selectDate,
                  child: InputDecorator(
                    decoration: const InputDecoration(
                      labelText: 'Data',
                      prefixIcon: Icon(Icons.calendar_today),
                    ),
                    child: Text(
                      DateFormat('dd/MM/yyyy').format(_selectedDate),
                    ),
                  ),
                ),
                
                const SizedBox(height: 16),
                
                // Método de pagamento
                DropdownButtonFormField<String>(
                  value: _paymentMethod,
                  decoration: const InputDecoration(
                    labelText: 'Método de Pagamento',
                    prefixIcon: Icon(Icons.payment),
                  ),
                  items: _paymentMethods
                      .map((method) => DropdownMenuItem(
                            value: method,
                            child: Text(method),
                          ))
                      .toList(),
                  onChanged: (method) {
                    setState(() {
                      _paymentMethod = method;
                    });
                  },
                ),
                
                const SizedBox(height: 32),
                
                // Botão salvar (mobile)
                if (MediaQuery.of(context).size.width < 600) ...[
                  ElevatedButton(
                    onPressed: transactionProvider.isLoading ? null : _saveTransaction,
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: transactionProvider.isLoading
                          ? const CircularProgressIndicator()
                          : Text(_isEditing ? 'Atualizar Transação' : 'Criar Transação'),
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}