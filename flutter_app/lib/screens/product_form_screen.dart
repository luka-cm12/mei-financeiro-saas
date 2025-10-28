import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../models/nfce_model.dart';
import '../services/nfce_service.dart';
import '../widgets/loading_dialog.dart';
import '../widgets/success_dialog.dart';
import '../widgets/error_dialog.dart';

class ProductFormScreen extends StatefulWidget {
  final Product? product;

  const ProductFormScreen({Key? key, this.product}) : super(key: key);

  @override
  State<ProductFormScreen> createState() => _ProductFormScreenState();
}

class _ProductFormScreenState extends State<ProductFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nfceService = NFCeService();
  
  // Controllers
  final _nameController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _priceController = TextEditingController();
  final _ncmController = TextEditingController();
  
  // Dropdowns
  String _selectedUnit = 'UN';
  String _selectedCfop = '5102';
  String _icmsOrigin = '0';
  String _icmsTaxSituation = '102';
  String _pisTaxSituation = '07';
  String _cofinsTaxSituation = '07';
  
  bool _isLoading = false;
  bool get _isEditing => widget.product != null;

  @override
  void initState() {
    super.initState();
    _initializeForm();
  }

  void _initializeForm() {
    if (_isEditing) {
      final product = widget.product!;
      
      _nameController.text = product.name;
      _descriptionController.text = product.description ?? '';
      _priceController.text = product.price.toStringAsFixed(2).replaceAll('.', ',');
      _ncmController.text = product.ncm ?? '';
      _selectedUnit = product.unit;
      _selectedCfop = product.cfop;
      _icmsOrigin = product.icmsOrigin;
      _icmsTaxSituation = product.icmsTaxSituation;
      _pisTaxSituation = product.pisTaxSituation;
      _cofinsTaxSituation = product.cofinsTaxSituation;
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _priceController.dispose();
    _ncmController.dispose();
    super.dispose();
  }

  double _parsePrice(String text) {
    return double.tryParse(text.replaceAll(',', '.')) ?? 0.0;
  }

  Future<void> _saveProduct() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => LoadingDialog(
          message: _isEditing ? 'Atualizando produto...' : 'Criando produto...',
        ),
      );

      final product = Product(
        id: _isEditing ? widget.product!.id : null,
        name: _nameController.text.trim(),
        description: _descriptionController.text.trim().isNotEmpty
            ? _descriptionController.text.trim()
            : null,
        price: _parsePrice(_priceController.text),
        unit: _selectedUnit,
        ncm: _ncmController.text.trim().isNotEmpty
            ? _ncmController.text.trim()
            : null,
        cfop: _selectedCfop,
        icmsOrigin: _icmsOrigin,
        icmsTaxSituation: _icmsTaxSituation,
        pisTaxSituation: _pisTaxSituation,
        cofinsTaxSituation: _cofinsTaxSituation,
      );

      await _nfceService.saveProduct(product);

      Navigator.pop(context); // Fechar loading

      await showDialog(
        context: context,
        builder: (context) => SuccessDialog(
          title: _isEditing ? 'Produto Atualizado!' : 'Produto Criado!',
          message: _isEditing
              ? 'O produto foi atualizado com sucesso.'
              : 'O produto foi criado com sucesso.',
        ),
      );

      Navigator.pop(context, true); // Voltar com sucesso

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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_isEditing ? 'Editar Produto' : 'Novo Produto'),
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        actions: [
          TextButton(
            onPressed: _isLoading ? null : _saveProduct,
            child: const Text(
              'Salvar',
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildBasicInfoSection(),
              const SizedBox(height: 24),
              _buildFiscalInfoSection(),
              const SizedBox(height: 24),
              _buildTaxInfoSection(),
              const SizedBox(height: 32),
              _buildSaveButton(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBasicInfoSection() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.inventory_2, color: Colors.green),
                SizedBox(width: 8),
                Text(
                  'Informações Básicas',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'Nome do Produto/Serviço *',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.label),
              ),
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Nome é obrigatório';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _descriptionController,
              decoration: const InputDecoration(
                labelText: 'Descrição (Opcional)',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.description),
              ),
              maxLines: 3,
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: _priceController,
                    decoration: const InputDecoration(
                      labelText: 'Preço *',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.attach_money),
                      prefixText: 'R\$ ',
                    ),
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9,.]')),
                    ],
                    validator: (value) {
                      if (value == null || value.trim().isEmpty) {
                        return 'Preço é obrigatório';
                      }
                      
                      final price = _parsePrice(value);
                      if (price <= 0) {
                        return 'Preço deve ser maior que zero';
                      }
                      
                      return null;
                    },
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _selectedUnit,
                    decoration: const InputDecoration(
                      labelText: 'Unidade *',
                      border: OutlineInputBorder(),
                      prefixIcon: Icon(Icons.straighten),
                    ),
                    items: _nfceService.getUnitsOptions().entries.map((entry) {
                      return DropdownMenuItem(
                        value: entry.key,
                        child: Text(entry.value),
                      );
                    }).toList(),
                    onChanged: (value) {
                      setState(() {
                        _selectedUnit = value!;
                      });
                    },
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Selecione a unidade';
                      }
                      return null;
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFiscalInfoSection() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.receipt_long, color: Colors.blue),
                SizedBox(width: 8),
                Text(
                  'Informações Fiscais',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _ncmController,
              decoration: const InputDecoration(
                labelText: 'NCM (Opcional)',
                hintText: '12345678',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.qr_code),
              ),
              keyboardType: TextInputType.number,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(8),
              ],
              validator: (value) {
                if (value != null && value.isNotEmpty && value.length != 8) {
                  return 'NCM deve ter 8 dígitos';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              value: _selectedCfop,
              decoration: const InputDecoration(
                labelText: 'CFOP *',
                border: OutlineInputBorder(),
                prefixIcon: Icon(Icons.code),
              ),
              items: _nfceService.getCFOPOptions().entries.map((entry) {
                return DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value),
                );
              }).toList(),
              onChanged: (value) {
                setState(() {
                  _selectedCfop = value!;
                });
              },
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Selecione o CFOP';
                }
                return null;
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTaxInfoSection() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.account_balance, color: Colors.orange),
                SizedBox(width: 8),
                Text(
                  'Informações Tributárias',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Configurações padrão para Simples Nacional',
              style: TextStyle(
                color: Colors.grey.shade600,
                fontSize: 14,
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _icmsOrigin,
                    decoration: const InputDecoration(
                      labelText: 'Origem ICMS',
                      border: OutlineInputBorder(),
                    ),
                    items: const [
                      DropdownMenuItem(value: '0', child: Text('0 - Nacional')),
                      DropdownMenuItem(value: '1', child: Text('1 - Estrangeira (importação direta)')),
                      DropdownMenuItem(value: '2', child: Text('2 - Estrangeira (mercado interno)')),
                    ],
                    onChanged: (value) {
                      setState(() {
                        _icmsOrigin = value!;
                      });
                    },
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _icmsTaxSituation,
                    decoration: const InputDecoration(
                      labelText: 'CST ICMS',
                      border: OutlineInputBorder(),
                    ),
                    items: const [
                      DropdownMenuItem(value: '102', child: Text('102 - SN sem permissão')),
                      DropdownMenuItem(value: '103', child: Text('103 - Isenção')),
                      DropdownMenuItem(value: '300', child: Text('300 - Imune')),
                      DropdownMenuItem(value: '400', child: Text('400 - Não tributado')),
                    ],
                    onChanged: (value) {
                      setState(() {
                        _icmsTaxSituation = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _pisTaxSituation,
                    decoration: const InputDecoration(
                      labelText: 'CST PIS',
                      border: OutlineInputBorder(),
                    ),
                    items: const [
                      DropdownMenuItem(value: '07', child: Text('07 - Não tributado')),
                      DropdownMenuItem(value: '08', child: Text('08 - Sem incidência')),
                      DropdownMenuItem(value: '09', child: Text('09 - Suspensão')),
                    ],
                    onChanged: (value) {
                      setState(() {
                        _pisTaxSituation = value!;
                      });
                    },
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _cofinsTaxSituation,
                    decoration: const InputDecoration(
                      labelText: 'CST COFINS',
                      border: OutlineInputBorder(),
                    ),
                    items: const [
                      DropdownMenuItem(value: '07', child: Text('07 - Não tributado')),
                      DropdownMenuItem(value: '08', child: Text('08 - Sem incidência')),
                      DropdownMenuItem(value: '09', child: Text('09 - Suspensão')),
                    ],
                    onChanged: (value) {
                      setState(() {
                        _cofinsTaxSituation = value!;
                      });
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSaveButton() {
    return SizedBox(
      width: double.infinity,
      height: 50,
      child: ElevatedButton(
        onPressed: _isLoading ? null : _saveProduct,
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
            : Text(
                _isEditing ? 'Atualizar Produto' : 'Criar Produto',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
      ),
    );
  }
}