import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../models/nfce_model.dart';
import '../services/nfce_service.dart';
import '../widgets/loading_dialog.dart';
import '../widgets/success_dialog.dart';
import '../widgets/error_dialog.dart';
import 'product_selector_screen.dart';

class NFCeEmissionScreen extends StatefulWidget {
  const NFCeEmissionScreen({Key? key}) : super(key: key);

  @override
  State<NFCeEmissionScreen> createState() => _NFCeEmissionScreenState();
}

class _NFCeEmissionScreenState extends State<NFCeEmissionScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nfceService = NFCeService();
  
  // Controllers
  final _customerDocumentController = TextEditingController();
  final _customerNameController = TextEditingController();
  final _customerEmailController = TextEditingController();
  final _customerPhoneController = TextEditingController();
  final _discountController = TextEditingController(text: '0,00');
  final _paymentAmountController = TextEditingController();
  
  // Dados da NFCe
  List<NFCeItem> _items = [];
  String _paymentMethod = 'money';
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _paymentAmountController.addListener(_calculateChange);
    _discountController.text = '0,00';
  }

  @override
  void dispose() {
    _customerDocumentController.dispose();
    _customerNameController.dispose();
    _customerEmailController.dispose();
    _customerPhoneController.dispose();
    _discountController.dispose();
    _paymentAmountController.dispose();
    super.dispose();
  }

  double get _subtotal => _items.fold(0, (sum, item) => sum + item.totalPrice);
  double get _discount => _parseAmount(_discountController.text);
  double get _total => _subtotal - _discount;
  double get _paymentAmount => _parseAmount(_paymentAmountController.text);
  double get _change => _paymentAmount > _total ? _paymentAmount - _total : 0;

  double _parseAmount(String text) {
    return double.tryParse(text.replaceAll(',', '.').replaceAll('R\$', '').trim()) ?? 0;
  }

  void _calculateChange() {
    setState(() {});
  }

  void _addProduct() async {
    final result = await Navigator.push<Product>(
      context,
      MaterialPageRoute(
        builder: (context) => const ProductSelectorScreen(),
      ),
    );

    if (result != null) {
      _showQuantityDialog(result);
    }
  }

  void _showQuantityDialog(Product product) {
    final quantityController = TextEditingController(text: '1');
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Quantidade'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Produto: ${product.name}'),
            Text('Preço unitário: ${product.formattedPrice}'),
            const SizedBox(height: 16),
            TextFormField(
              controller: quantityController,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'Quantidade',
                border: OutlineInputBorder(),
              ),
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[0-9,.]')),
              ],
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () {
              final quantity = double.tryParse(
                quantityController.text.replaceAll(',', '.')
              ) ?? 1;
              
              if (quantity > 0) {
                setState(() {
                  _items.add(NFCeItem.fromProduct(product, quantity));
                });
                Navigator.pop(context);
              }
            },
            child: const Text('Adicionar'),
          ),
        ],
      ),
    );
  }

  void _removeItem(int index) {
    setState(() {
      _items.removeAt(index);
    });
  }

  void _editItem(int index) {
    final item = _items[index];
    final quantityController = TextEditingController(
      text: item.quantity.toString().replaceAll('.', ',')
    );
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Editar Quantidade'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Produto: ${item.description}'),
            Text('Preço unitário: ${item.formattedUnitPrice}'),
            const SizedBox(height: 16),
            TextFormField(
              controller: quantityController,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'Quantidade',
                border: OutlineInputBorder(),
              ),
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[0-9,.]')),
              ],
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () {
              final quantity = double.tryParse(
                quantityController.text.replaceAll(',', '.')
              ) ?? 1;
              
              if (quantity > 0) {
                setState(() {
                  _items[index] = item.copyWith(
                    quantity: quantity,
                    totalPrice: quantity * item.unitPrice,
                  );
                });
                Navigator.pop(context);
              }
            },
            child: const Text('Salvar'),
          ),
        ],
      ),
    );
  }

  void _emitNFCe() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (_items.isEmpty) {
      showDialog(
        context: context,
        builder: (context) => const ErrorDialog(
          message: 'Adicione pelo menos um produto à NFCe',
        ),
      );
      return;
    }

    if (_paymentAmount < _total) {
      showDialog(
        context: context,
        builder: (context) => const ErrorDialog(
          message: 'Valor pago deve ser maior ou igual ao total',
        ),
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const LoadingDialog(
          message: 'Emitindo NFCe...',
        ),
      );

      final nfce = await _nfceService.emitNFCe(
        items: _items,
        customerDocument: _customerDocumentController.text.isNotEmpty 
          ? _customerDocumentController.text 
          : null,
        customerName: _customerNameController.text.isNotEmpty 
          ? _customerNameController.text 
          : null,
        customerEmail: _customerEmailController.text.isNotEmpty 
          ? _customerEmailController.text 
          : null,
        customerPhone: _customerPhoneController.text.isNotEmpty 
          ? _customerPhoneController.text 
          : null,
        totalDiscounts: _discount,
        paymentMethod: _paymentMethod,
        paymentAmount: _paymentAmount,
        changeAmount: _change,
      );

      Navigator.pop(context); // Fechar loading

      await showDialog(
        context: context,
        builder: (context) => SuccessDialog(
          title: 'NFCe Emitida!',
          message: 'NFCe ${nfce.nfceNumber} emitida com sucesso.\n'
                  'Status: ${nfce.statusDisplay}\n'
                  'Total: ${nfce.formattedTotalAmount}',
        ),
      );

      _clearForm();

    } catch (e) {
      Navigator.pop(context); // Fechar loading
      
      showDialog(
        context: context,
        builder: (context) => ErrorDialog(
          message: e.toString().replaceAll('Exception: ', ''),
        ),
      );
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  void _clearForm() {
    setState(() {
      _items.clear();
      _customerDocumentController.clear();
      _customerNameController.clear();
      _customerEmailController.clear();
      _customerPhoneController.clear();
      _discountController.text = '0,00';
      _paymentAmountController.clear();
      _paymentMethod = 'money';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Emitir NFCe'),
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            onPressed: _clearForm,
            icon: const Icon(Icons.clear_all),
            tooltip: 'Limpar tudo',
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildItemsSection(),
                    const SizedBox(height: 24),
                    _buildCustomerSection(),
                    const SizedBox(height: 24),
                    _buildPaymentSection(),
                  ],
                ),
              ),
            ),
            _buildBottomSection(),
          ],
        ),
      ),
    );
  }

  Widget _buildItemsSection() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.shopping_cart, color: Colors.green),
                const SizedBox(width: 8),
                const Text(
                  'Produtos/Serviços',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const Spacer(),
                ElevatedButton.icon(
                  onPressed: _addProduct,
                  icon: const Icon(Icons.add),
                  label: const Text('Adicionar'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    foregroundColor: Colors.white,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            if (_items.isEmpty)
              Container(
                padding: const EdgeInsets.all(32),
                width: double.infinity,
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.grey.shade300),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  children: [
                    Icon(
                      Icons.shopping_cart_outlined,
                      size: 48,
                      color: Colors.grey.shade400,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Nenhum produto adicionado',
                      style: TextStyle(
                        color: Colors.grey.shade600,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Toque em "Adicionar" para incluir produtos',
                      style: TextStyle(
                        color: Colors.grey.shade500,
                        fontSize: 14,
                      ),
                    ),
                  ],
                ),
              )
            else
              Column(
                children: _items.asMap().entries.map((entry) {
                  final index = entry.key;
                  final item = entry.value;
                  
                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(
                        item.description,
                        style: const TextStyle(fontWeight: FontWeight.w500),
                      ),
                      subtitle: Text(
                        '${item.formattedQuantity} x ${item.formattedUnitPrice}',
                      ),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            item.formattedTotalPrice,
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                          PopupMenuButton(
                            itemBuilder: (context) => [
                              PopupMenuItem(
                                value: 'edit',
                                child: const Row(
                                  children: [
                                    Icon(Icons.edit, size: 20),
                                    SizedBox(width: 8),
                                    Text('Editar'),
                                  ],
                                ),
                              ),
                              PopupMenuItem(
                                value: 'remove',
                                child: const Row(
                                  children: [
                                    Icon(Icons.delete, size: 20, color: Colors.red),
                                    SizedBox(width: 8),
                                    Text('Remover', style: TextStyle(color: Colors.red)),
                                  ],
                                ),
                              ),
                            ],
                            onSelected: (value) {
                              if (value == 'edit') {
                                _editItem(index);
                              } else if (value == 'remove') {
                                _removeItem(index);
                              }
                            },
                          ),
                        ],
                      ),
                    ),
                  );
                }).toList(),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildCustomerSection() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.person, color: Colors.blue),
                SizedBox(width: 8),
                Text(
                  'Cliente (Opcional)',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _customerDocumentController,
              decoration: const InputDecoration(
                labelText: 'CPF/CNPJ',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.badge),
              ),
              keyboardType: TextInputType.number,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(14),
              ],
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _customerNameController,
              decoration: const InputDecoration(
                labelText: 'Nome/Razão Social',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.person_outline),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _customerEmailController,
                    decoration: const InputDecoration(
                      labelText: 'E-mail',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.email),
                    ),
                    keyboardType: TextInputType.emailAddress,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: TextFormField(
                    controller: _customerPhoneController,
                    decoration: const InputDecoration(
                      labelText: 'Telefone',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.phone),
                    ),
                    keyboardType: TextInputType.phone,
                    inputFormatters: [
                      FilteringTextInputFormatter.digitsOnly,
                      LengthLimitingTextInputFormatter(11),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentSection() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.payment, color: Colors.orange),
                SizedBox(width: 8),
                Text(
                  'Pagamento',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _discountController,
              decoration: const InputDecoration(
                labelText: 'Desconto',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.discount),
                prefixText: 'R\$ ',
              ),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[0-9,.]')),
              ],
              onChanged: (value) => setState(() {}),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              value: _paymentMethod,
              decoration: const InputDecoration(
                labelText: 'Forma de Pagamento',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.credit_card),
              ),
              items: _nfceService.getPaymentMethods().entries.map((entry) {
                return DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value),
                );
              }).toList(),
              onChanged: (value) {
                setState(() {
                  _paymentMethod = value!;
                });
              },
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Selecione a forma de pagamento';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _paymentAmountController,
              decoration: const InputDecoration(
                labelText: 'Valor Pago',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.attach_money),
                prefixText: 'R\$ ',
              ),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[0-9,.]')),
              ],
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Informe o valor pago';
                }
                
                final amount = _parseAmount(value);
                if (amount < _total) {
                  return 'Valor pago deve ser maior ou igual ao total';
                }
                
                return null;
              },
              onChanged: (value) => setState(() {}),
            ),
            if (_change > 0) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.green.shade50,
                  border: Border.all(color: Colors.green.shade200),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    Icon(Icons.monetization_on, color: Colors.green.shade600),
                    const SizedBox(width: 8),
                    Text(
                      'Troco: ',
                      style: TextStyle(
                        color: Colors.green.shade600,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    Text(
                      _nfceService.formatCurrency(_change),
                      style: TextStyle(
                        color: Colors.green.shade600,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildBottomSection() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        border: Border(
          top: BorderSide(color: Colors.grey.shade300),
        ),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Subtotal: ${_nfceService.formatCurrency(_subtotal)}',
                      style: const TextStyle(fontSize: 14),
                    ),
                    if (_discount > 0)
                      Text(
                        'Desconto: ${_nfceService.formatCurrency(_discount)}',
                        style: const TextStyle(
                          fontSize: 14,
                          color: Colors.red,
                        ),
                      ),
                    Text(
                      'Total: ${_nfceService.formatCurrency(_total)}',
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: Colors.green,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 16),
              SizedBox(
                width: 150,
                height: 50,
                child: ElevatedButton(
                  onPressed: _items.isNotEmpty && !_isLoading ? _emitNFCe : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  child: _isLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2,
                          ),
                        )
                      : const Text(
                          'Emitir NFCe',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}