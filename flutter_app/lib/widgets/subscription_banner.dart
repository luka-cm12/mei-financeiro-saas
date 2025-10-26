import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';

class SubscriptionBanner extends StatelessWidget {
  const SubscriptionBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, child) {
        final user = authProvider.user;
        if (user == null) return const SizedBox.shrink();

        final isTrialExpired = !user.hasActiveSubscription;
        final daysLeft = user.daysUntilExpiration;
        
        Color backgroundColor;
        Color textColor;
        String message;
        IconData icon;

        if (isTrialExpired) {
          backgroundColor = Colors.red[100]!;
          textColor = Colors.red[700]!;
          message = 'Sua assinatura expirou. Renove para continuar usando.';
          icon = Icons.error;
        } else if (user.subscriptionStatus == 'trial') {
          if (daysLeft <= 3) {
            backgroundColor = Colors.orange[100]!;
            textColor = Colors.orange[700]!;
            message = 'Seu período gratuito expira em $daysLeft ${daysLeft == 1 ? 'dia' : 'dias'}.';
            icon = Icons.warning;
          } else {
            backgroundColor = Colors.blue[100]!;
            textColor = Colors.blue[700]!;
            message = 'Período gratuito: $daysLeft ${daysLeft == 1 ? 'dia' : 'dias'} restantes.';
            icon = Icons.info;
          }
        } else {
          // Assinatura ativa - não mostrar banner
          return const SizedBox.shrink();
        }

        return Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: backgroundColor,
            border: Border(
              bottom: BorderSide(
                color: textColor.withOpacity(0.2),
                width: 1,
              ),
            ),
          ),
          child: Row(
            children: [
              Icon(
                icon,
                color: textColor,
                size: 20,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  message,
                  style: TextStyle(
                    color: textColor,
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              ElevatedButton(
                onPressed: () => _showSubscriptionDialog(context),
                style: ElevatedButton.styleFrom(
                  backgroundColor: textColor,
                  foregroundColor: backgroundColor,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  minimumSize: Size.zero,
                ),
                child: Text(
                  isTrialExpired ? 'Renovar' : 'Assinar',
                  style: const TextStyle(fontSize: 12),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  void _showSubscriptionDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.star, color: Colors.amber),
            SizedBox(width: 8),
            Text('MEI Financeiro Pro'),
          ],
        ),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Desbloqueie todos os recursos:',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
            SizedBox(height: 12),
            _FeatureItem(text: '✓ Transações ilimitadas'),
            _FeatureItem(text: '✓ Relatórios avançados'),
            _FeatureItem(text: '✓ Gráficos detalhados'),
            _FeatureItem(text: '✓ Backup automático'),
            _FeatureItem(text: '✓ Suporte prioritário'),
            SizedBox(height: 16),
            Text(
              'Apenas R\$ 19,90/mês',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: Colors.green,
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Mais tarde'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              _openPaymentPage(context);
            },
            child: const Text('Assinar Agora'),
          ),
        ],
      ),
    );
  }

  void _openPaymentPage(BuildContext context) {
    // TODO: Implementar página de pagamento
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Integração com pagamento em desenvolvimento'),
        backgroundColor: Colors.blue,
      ),
    );
  }
}

class _FeatureItem extends StatelessWidget {
  final String text;

  const _FeatureItem({required this.text});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Text(
        text,
        style: const TextStyle(fontSize: 14),
      ),
    );
  }
}