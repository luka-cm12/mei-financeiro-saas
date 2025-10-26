import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/subscription_provider.dart';
import '../services/subscription_service.dart';
import 'payment_screen.dart';

class SubscriptionPlansScreen extends StatefulWidget {
  const SubscriptionPlansScreen({Key? key}) : super(key: key);

  @override
  State<SubscriptionPlansScreen> createState() => _SubscriptionPlansScreenState();
}

class _SubscriptionPlansScreenState extends State<SubscriptionPlansScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<SubscriptionProvider>().loadPlans();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Planos de Assinatura'),
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Consumer<SubscriptionProvider>(
        builder: (context, provider, child) {
          if (provider.isLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          if (provider.error != null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
                  const SizedBox(height: 16),
                  Text(
                    'Erro ao carregar planos',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    provider.error!,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.grey[600],
                    ),
                  ),
                  const SizedBox(height: 24),
                  ElevatedButton(
                    onPressed: provider.loadPlans,
                    child: const Text('Tentar Novamente'),
                  ),
                ],
              ),
            );
          }

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildHeader(context, provider),
                const SizedBox(height: 24),
                _buildCurrentPlanCard(context, provider),
                const SizedBox(height: 32),
                Text(
                  'Escolha seu plano',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 16),
                ...provider.plans.map((plan) => _buildPlanCard(context, provider, plan)),
                const SizedBox(height: 32),
                _buildFeatureComparison(context, provider),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildHeader(BuildContext context, SubscriptionProvider provider) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [Colors.green[400]!, Colors.green[600]!],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.workspace_premium, size: 40, color: Colors.white),
          const SizedBox(height: 12),
          Text(
            'Evolua seu negócio',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              color: Colors.white,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Desbloqueie recursos avançados para gerenciar suas finanças como um profissional',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Colors.white.withOpacity(0.9),
            ),
          ),
          if (provider.getYearlySavings() > 0) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: Colors.orange,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                'Economize R\$ ${provider.getYearlySavings().toStringAsFixed(2)} com o plano anual!',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildCurrentPlanCard(BuildContext context, SubscriptionProvider provider) {
    final currentSub = provider.currentSubscription;
    if (currentSub == null) return const SizedBox.shrink();

    return Card(
      elevation: 4,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: _getPlanColor(currentSub.planSlug),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(
                    _getPlanIcon(currentSub.planSlug),
                    color: Colors.white,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Plano Atual',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey[600],
                        ),
                      ),
                      Text(
                        currentSub.planName,
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
                _buildStatusChip(currentSub),
              ],
            ),
            const SizedBox(height: 16),
            if (currentSub.isTrial) ...[
              _buildTrialInfo(context, currentSub),
              const SizedBox(height: 12),
            ],
            if (currentSub.endsAt != null && !currentSub.isFree) ...[
              Row(
                children: [
                  Icon(Icons.calendar_today, size: 16, color: Colors.grey[600]),
                  const SizedBox(width: 8),
                  Text(
                    'Válido até ${_formatDate(currentSub.endsAt!)}',
                    style: TextStyle(color: Colors.grey[600]),
                  ),
                ],
              ),
              const SizedBox(height: 8),
            ],
            Row(
              children: [
                Icon(Icons.trending_up, size: 16, color: Colors.grey[600]),
                const SizedBox(width: 8),
                Text(
                  '${currentSub.daysRemaining} dias restantes',
                  style: TextStyle(color: Colors.grey[600]),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTrialInfo(BuildContext context, UserSubscription subscription) {
    final isExpiring = subscription.isTrialExpiring;
    
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isExpiring ? Colors.orange[50] : Colors.blue[50],
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isExpiring ? Colors.orange[200]! : Colors.blue[200]!,
        ),
      ),
      child: Row(
        children: [
          Icon(
            isExpiring ? Icons.warning : Icons.info,
            color: isExpiring ? Colors.orange : Colors.blue,
            size: 20,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              isExpiring
                  ? 'Seu trial expira em ${subscription.trialDaysRemaining} dias'
                  : 'Trial ativo - ${subscription.trialDaysRemaining} dias restantes',
              style: TextStyle(
                color: isExpiring ? Colors.orange[800] : Colors.blue[800],
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusChip(UserSubscription subscription) {
    Color color;
    String label;
    
    if (subscription.isTrial) {
      color = Colors.blue;
      label = 'TRIAL';
    } else if (subscription.isFree) {
      color = Colors.grey;
      label = 'GRATUITO';
    } else if (subscription.isActive) {
      color = Colors.green;
      label = 'ATIVO';
    } else {
      color = Colors.red;
      label = 'INATIVO';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 10,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  Widget _buildPlanCard(BuildContext context, SubscriptionProvider provider, SubscriptionPlan plan) {
    final isCurrentPlan = provider.currentSubscription?.planSlug == plan.slug;
    final isRecommended = provider.getRecommendedPlan()?.slug == plan.slug;

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      child: Stack(
        children: [
          Card(
            elevation: isRecommended ? 8 : 2,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: BorderSide(
                color: isRecommended ? Colors.orange : Colors.transparent,
                width: 2,
              ),
            ),
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
                          color: _getPlanColor(plan.slug),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Icon(
                          _getPlanIcon(plan.slug),
                          color: Colors.white,
                          size: 24,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              plan.name,
                              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              plan.description,
                              style: TextStyle(color: Colors.grey[600]),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      if (plan.isFree) ...[
                        Text(
                          'Gratuito',
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: Colors.green,
                          ),
                        ),
                      ] else ...[
                        Text(
                          'R\$ ${plan.price.toStringAsFixed(2).replaceAll('.', ',')}',
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: Colors.green,
                          ),
                        ),
                        Text(
                          plan.billingPeriod == 'yearly' ? '/ano' : '/mês',
                          style: TextStyle(color: Colors.grey[600]),
                        ),
                      ],
                      const Spacer(),
                      if (plan.billingPeriod == 'yearly' && !plan.isFree) ...[
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.green[100],
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            'Economize 20%',
                            style: TextStyle(
                              color: Colors.green[800],
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 20),
                  ...plan.features.map((feature) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      children: [
                        Icon(Icons.check_circle, color: Colors.green, size: 16),
                        const SizedBox(width: 8),
                        Expanded(child: Text(feature)),
                      ],
                    ),
                  )),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: isCurrentPlan ? null : () => _handlePlanSelection(context, provider, plan),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: isCurrentPlan ? Colors.grey : _getPlanColor(plan.slug),
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: Text(
                        isCurrentPlan
                            ? 'Plano Atual'
                            : plan.isFree
                                ? 'Usar Gratuito'
                                : 'Escolher Plano',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (isRecommended)
            Positioned(
              top: -1,
              right: -1,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: const BoxDecoration(
                  color: Colors.orange,
                  borderRadius: BorderRadius.only(
                    topRight: Radius.circular(14),
                    bottomLeft: Radius.circular(14),
                  ),
                ),
                child: const Text(
                  'RECOMENDADO',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildFeatureComparison(BuildContext context, SubscriptionProvider provider) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Comparar recursos',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 16),
            _buildComparisonRow('Transações por mês', ['100', 'Ilimitado', 'Ilimitado', 'Ilimitado']),
            _buildComparisonRow('Categorias', ['10', 'Ilimitado', 'Ilimitado', 'Ilimitado']),
            _buildComparisonRow('Metas financeiras', ['2', 'Ilimitado', 'Ilimitado', 'Ilimitado']),
            _buildComparisonRow('Relatórios', ['Básicos', 'Avançados', 'Avançados', 'Empresariais']),
            _buildComparisonRow('Backup', ['Mensal', 'Diário', 'Diário', 'Diário']),
            _buildComparisonRow('Suporte', ['Email', 'Prioritário', 'Prioritário', 'Dedicado']),
          ],
        ),
      ),
    );
  }

  Widget _buildComparisonRow(String feature, List<String> values) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Expanded(
            flex: 2,
            child: Text(
              feature,
              style: const TextStyle(fontWeight: FontWeight.w500),
            ),
          ),
          ...values.map((value) => Expanded(
            child: Text(
              value,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 12,
                color: Colors.grey[700],
              ),
            ),
          )),
        ],
      ),
    );
  }

  void _handlePlanSelection(BuildContext context, SubscriptionProvider provider, SubscriptionPlan plan) {
    if (plan.isFree) {
      _showDowngradeConfirmation(context, provider, plan);
    } else {
      _showPlanOptions(context, provider, plan);
    }
  }

  void _showPlanOptions(BuildContext context, SubscriptionProvider provider, SubscriptionPlan plan) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.6,
        maxChildSize: 0.8,
        builder: (context, scrollController) => Column(
          children: [
            Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.symmetric(vertical: 12),
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    Text(
                      'Escolha uma opção',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 24),
                    _buildOptionCard(
                      context,
                      'Começar Trial Gratuito',
                      '7 dias grátis para testar',
                      Icons.free_breakfast,
                      Colors.blue,
                      () => _startTrial(context, provider, plan),
                    ),
                    const SizedBox(height: 16),
                    _buildOptionCard(
                      context,
                      'Assinar Agora',
                      'Acesso completo imediato',
                      Icons.payment,
                      Colors.green,
                      () => _startSubscription(context, provider, plan),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOptionCard(
    BuildContext context,
    String title,
    String subtitle,
    IconData icon,
    Color color,
    VoidCallback onTap,
  ) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: color,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: Colors.white, size: 24),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: TextStyle(color: Colors.grey[600]),
                    ),
                  ],
                ),
              ),
              Icon(Icons.arrow_forward_ios, color: Colors.grey[400], size: 16),
            ],
          ),
        ),
      ),
    );
  }

  void _showDowngradeConfirmation(BuildContext context, SubscriptionProvider provider, SubscriptionPlan plan) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirmar downgrade'),
        content: const Text('Você perderá acesso aos recursos premium. Deseja continuar?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              _downgradeToPlan(context, provider, plan);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Confirmar', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  Future<void> _startTrial(BuildContext context, SubscriptionProvider provider, SubscriptionPlan plan) async {
    Navigator.pop(context);
    final success = await provider.startTrial(plan.slug);
    
    if (!mounted) return;
    
    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Trial de 7 dias iniciado para ${plan.name}!'),
          backgroundColor: Colors.green,
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Erro: ${provider.error}'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _startSubscription(BuildContext context, SubscriptionProvider provider, SubscriptionPlan plan) async {
    Navigator.pop(context);
    
    // Navegar para tela de pagamento
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => PaymentScreen(plan: plan),
      ),
    );
  }

  Future<void> _downgradeToPlan(BuildContext context, SubscriptionProvider provider, SubscriptionPlan plan) async {
    final success = await provider.upgrade(plan.slug);
    
    if (!mounted) return;
    
    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Plano alterado para ${plan.name}'),
          backgroundColor: Colors.green,
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Erro: ${provider.error}'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Color _getPlanColor(String slug) {
    switch (slug) {
      case 'free':
        return Colors.grey;
      case 'premium':
        return Colors.blue;
      case 'premium-yearly':
        return Colors.purple;
      case 'business':
        return Colors.orange;
      default:
        return Colors.grey;
    }
  }

  IconData _getPlanIcon(String slug) {
    switch (slug) {
      case 'free':
        return Icons.person;
      case 'premium':
        return Icons.star;
      case 'premium-yearly':
        return Icons.workspace_premium;
      case 'business':
        return Icons.business;
      default:
        return Icons.person;
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  }
}