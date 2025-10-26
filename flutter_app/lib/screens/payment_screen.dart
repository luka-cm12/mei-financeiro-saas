import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/subscription_service.dart';
import '../providers/subscription_provider.dart';

class PaymentScreen extends StatefulWidget {
  final SubscriptionPlan plan;
  
  const PaymentScreen({Key? key, required this.plan}) : super(key: key);

  @override
  State<PaymentScreen> createState() => _PaymentScreenState();
}

class _PaymentScreenState extends State<PaymentScreen> {
  final _formKey = GlobalKey<FormState>();
  final _cardNumberController = TextEditingController();
  final _expiryController = TextEditingController();
  final _cvvController = TextEditingController();
  final _nameController = TextEditingController();
  
  String _selectedPaymentMethod = 'credit_card';
  bool _isProcessing = false;

  @override
  void dispose() {
    _cardNumberController.dispose();
    _expiryController.dispose();
    _cvvController.dispose();
    _nameController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pagamento'),
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildPlanSummary(),
              const SizedBox(height: 32),
              _buildPaymentMethods(),
              const SizedBox(height: 24),
              if (_selectedPaymentMethod == 'credit_card') ...[
                _buildCreditCardForm(),
                const SizedBox(height: 24),
              ],
              if (_selectedPaymentMethod == 'pix') ...[
                _buildPixInfo(),
                const SizedBox(height: 24),
              ],
              _buildSecurityInfo(),
              const SizedBox(height: 32),
              _buildPaymentButton(),
              const SizedBox(height: 16),
              _buildTermsAndConditions(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildPlanSummary() {
    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.green,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.star, color: Colors.white, size: 24),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Resumo da Assinatura',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey[600],
                        ),
                      ),
                      Text(
                        widget.plan.name,
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Divider(),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Subtotal:'),
                Text('R\$ ${widget.plan.price.toStringAsFixed(2).replaceAll('.', ',')}'),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Impostos:'),
                Text('R\$ 0,00'),
              ],
            ),
            const SizedBox(height: 8),
            const Divider(),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Total:',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Text(
                  widget.plan.priceFormatted,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: Colors.green,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'Cobrança ${widget.plan.billingPeriod == 'yearly' ? 'anual' : 'mensal'}',
              style: TextStyle(
                fontSize: 12,
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentMethods() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Forma de pagamento',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 16),
        _buildPaymentMethodOption(
          'credit_card',
          'Cartão de Crédito',
          Icons.credit_card,
          'Visa, Mastercard, Elo, Amex',
        ),
        const SizedBox(height: 12),
        _buildPaymentMethodOption(
          'pix',
          'PIX',
          Icons.qr_code,
          'Pagamento instantâneo via PIX',
        ),
        const SizedBox(height: 12),
        _buildPaymentMethodOption(
          'boleto',
          'Boleto Bancário',
          Icons.receipt_long,
          'Vencimento em 3 dias úteis',
        ),
      ],
    );
  }

  Widget _buildPaymentMethodOption(String value, String title, IconData icon, String subtitle) {
    final isSelected = _selectedPaymentMethod == value;
    
    return Card(
      elevation: isSelected ? 4 : 1,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: isSelected ? Colors.green : Colors.transparent,
          width: 2,
        ),
      ),
      child: InkWell(
        onTap: () => setState(() => _selectedPaymentMethod = value),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: isSelected ? Colors.green : Colors.grey[300],
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  icon,
                  color: isSelected ? Colors.white : Colors.grey[600],
                  size: 20,
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        fontWeight: FontWeight.w500,
                        color: isSelected ? Colors.green : Colors.black,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey[600],
                      ),
                    ),
                  ],
                ),
              ),
              Radio<String>(
                value: value,
                groupValue: _selectedPaymentMethod,
                onChanged: (val) => setState(() => _selectedPaymentMethod = val!),
                activeColor: Colors.green,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCreditCardForm() {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Dados do cartão',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'Nome no cartão',
                hintText: 'Como está impresso no cartão',
                prefixIcon: Icon(Icons.person),
                border: OutlineInputBorder(),
              ),
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Nome é obrigatório';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _cardNumberController,
              decoration: const InputDecoration(
                labelText: 'Número do cartão',
                hintText: '0000 0000 0000 0000',
                prefixIcon: Icon(Icons.credit_card),
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.number,
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Número do cartão é obrigatório';
                }
                if (value.replaceAll(' ', '').length < 13) {
                  return 'Número do cartão inválido';
                }
                return null;
              },
              onChanged: (value) {
                // Formatação automática do número do cartão
                String formatted = value.replaceAll(' ', '');
                if (formatted.length <= 16) {
                  String newValue = '';
                  for (int i = 0; i < formatted.length; i += 4) {
                    if (i > 0) newValue += ' ';
                    newValue += formatted.substring(i, (i + 4 > formatted.length) ? formatted.length : i + 4);
                  }
                  if (newValue != value) {
                    _cardNumberController.value = TextEditingValue(
                      text: newValue,
                      selection: TextSelection.collapsed(offset: newValue.length),
                    );
                  }
                }
              },
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _expiryController,
                    decoration: const InputDecoration(
                      labelText: 'Validade',
                      hintText: 'MM/AA',
                      prefixIcon: Icon(Icons.calendar_today),
                      border: OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.number,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Validade é obrigatória';
                      }
                      if (!RegExp(r'^\d{2}/\d{2}$').hasMatch(value)) {
                        return 'Formato inválido (MM/AA)';
                      }
                      return null;
                    },
                    onChanged: (value) {
                      // Formatação automática da validade
                      String formatted = value.replaceAll('/', '');
                      if (formatted.length >= 2) {
                        formatted = '${formatted.substring(0, 2)}/${formatted.substring(2)}';
                        if (formatted != value) {
                          _expiryController.value = TextEditingValue(
                            text: formatted,
                            selection: TextSelection.collapsed(offset: formatted.length),
                          );
                        }
                      }
                    },
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: TextFormField(
                    controller: _cvvController,
                    decoration: const InputDecoration(
                      labelText: 'CVV',
                      hintText: '123',
                      prefixIcon: Icon(Icons.security),
                      border: OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.number,
                    obscureText: true,
                    maxLength: 4,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'CVV é obrigatório';
                      }
                      if (value.length < 3) {
                        return 'CVV inválido';
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

  Widget _buildPixInfo() {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            Icon(Icons.qr_code, size: 64, color: Colors.green[600]),
            const SizedBox(height: 16),
            Text(
              'Pagamento via PIX',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Após confirmar, você receberá um código PIX para finalizar o pagamento.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[600]),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.blue[50],
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(Icons.info, color: Colors.blue[600], size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Pagamento instantâneo e seguro',
                      style: TextStyle(
                        color: Colors.blue[800],
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSecurityInfo() {
    return Card(
      elevation: 1,
      color: Colors.green[50],
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.security, color: Colors.green[600], size: 24),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Pagamento seguro',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: Colors.green[800],
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Seus dados são protegidos com criptografia SSL de 256 bits.',
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.green[700],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentButton() {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: _isProcessing ? null : _processPayment,
        style: ElevatedButton.styleFrom(
          backgroundColor: Colors.green,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        child: _isProcessing
            ? const SizedBox(
                height: 20,
                width: 20,
                child: CircularProgressIndicator(
                  color: Colors.white,
                  strokeWidth: 2,
                ),
              )
            : Text(
                'Finalizar Pagamento - ${widget.plan.priceFormatted}',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
      ),
    );
  }

  Widget _buildTermsAndConditions() {
    return Column(
      children: [
        Text(
          'Ao finalizar a compra, você concorda com nossos',
          style: TextStyle(fontSize: 12, color: Colors.grey[600]),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 4),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            InkWell(
              onTap: () => _showTermsDialog(),
              child: Text(
                'Termos de Uso',
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.green[600],
                  decoration: TextDecoration.underline,
                ),
              ),
            ),
            Text(
              ' e ',
              style: TextStyle(fontSize: 12, color: Colors.grey[600]),
            ),
            InkWell(
              onTap: () => _showPrivacyDialog(),
              child: Text(
                'Política de Privacidade',
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.green[600],
                  decoration: TextDecoration.underline,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _processPayment() async {
    if (_selectedPaymentMethod == 'credit_card' && !_formKey.currentState!.validate()) {
      return;
    }

    setState(() => _isProcessing = true);

    try {
      final subscriptionProvider = context.read<SubscriptionProvider>();
      
      // Simular processamento de pagamento
      await Future.delayed(const Duration(seconds: 2));
      
      // Em um app real, aqui você integraria com o gateway de pagamento
      final success = await subscriptionProvider.upgrade(widget.plan.slug);

      if (!mounted) return;

      if (success) {
        _showSuccessDialog();
      } else {
        _showErrorDialog(subscriptionProvider.error ?? 'Erro no processamento');
      }
    } catch (e) {
      if (!mounted) return;
      _showErrorDialog(e.toString());
    } finally {
      if (mounted) {
        setState(() => _isProcessing = false);
      }
    }
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Column(
          children: [
            Icon(Icons.check_circle, color: Colors.green, size: 64),
            const SizedBox(height: 16),
            const Text('Pagamento Aprovado!'),
          ],
        ),
        content: Text(
          'Sua assinatura ${widget.plan.name} foi ativada com sucesso.',
          textAlign: TextAlign.center,
        ),
        actions: [
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {
                Navigator.of(context).popUntil((route) => route.isFirst);
              },
              style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
              child: const Text('Continuar', style: TextStyle(color: Colors.white)),
            ),
          ),
        ],
      ),
    );
  }

  void _showErrorDialog(String error) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Column(
          children: [
            Icon(Icons.error, color: Colors.red, size: 64),
            const SizedBox(height: 16),
            const Text('Erro no Pagamento'),
          ],
        ),
        content: Text(
          error,
          textAlign: TextAlign.center,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Tentar Novamente'),
          ),
        ],
      ),
    );
  }

  void _showTermsDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Termos de Uso'),
        content: const SingleChildScrollView(
          child: Text(
            'Este é um exemplo dos termos de uso. Em um app real, você incluiria aqui os termos completos...',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Fechar'),
          ),
        ],
      ),
    );
  }

  void _showPrivacyDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Política de Privacidade'),
        content: const SingleChildScrollView(
          child: Text(
            'Este é um exemplo da política de privacidade. Em um app real, você incluiria aqui a política completa...',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Fechar'),
          ),
        ],
      ),
    );
  }
}