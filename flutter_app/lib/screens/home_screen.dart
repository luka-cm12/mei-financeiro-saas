import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/transaction_provider.dart';
import '../widgets/dashboard_card.dart';
import '../widgets/transaction_list_item.dart';
import '../widgets/subscription_banner.dart';
import '../utils/theme.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _loadInitialData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadInitialData() async {
    final transactionProvider = Provider.of<TransactionProvider>(context, listen: false);
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    
    await Future.wait([
      transactionProvider.loadCategories(),
      transactionProvider.loadTransactions(refresh: true),
      authProvider.checkSubscriptionStatus(),
    ]);
  }

  Future<void> _refreshData() async {
    await _loadInitialData();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('MEI Financeiro'),
        actions: [
          IconButton(
            icon: const Icon(Icons.filter_list),
            onPressed: _showFilters,
          ),
          PopupMenuButton(
            itemBuilder: (context) => [
              const PopupMenuItem(
                value: 'profile',
                child: Row(
                  children: [
                    Icon(Icons.person),
                    SizedBox(width: 8),
                    Text('Perfil'),
                  ],
                ),
              ),
              const PopupMenuItem(
                value: 'subscription',
                child: Row(
                  children: [
                    Icon(Icons.star),
                    SizedBox(width: 8),
                    Text('Assinatura'),
                  ],
                ),
              ),
              const PopupMenuItem(
                value: 'logout',
                child: Row(
                  children: [
                    Icon(Icons.logout),
                    SizedBox(width: 8),
                    Text('Sair'),
                  ],
                ),
              ),
            ],
            onSelected: _handleMenuAction,
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'Dashboard', icon: Icon(Icons.dashboard)),
            Tab(text: 'Transações', icon: Icon(Icons.list)),
            Tab(text: 'Relatórios', icon: Icon(Icons.analytics)),
          ],
        ),
      ),
      body: Column(
        children: [
          // Banner de assinatura (se necessário)
          Consumer<AuthProvider>(
            builder: (context, authProvider, child) {
              if (!authProvider.hasActiveSubscription) {
                return const SubscriptionBanner();
              }
              return const SizedBox.shrink();
            },
          ),
          
          // Conteúdo das tabs
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                _buildDashboardTab(),
                _buildTransactionsTab(),
                _buildReportsTab(),
              ],
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          Navigator.pushNamed(context, '/transaction-form').then((_) {
            // Atualizar dados após adicionar transação
            _refreshData();
          });
        },
        child: const Icon(Icons.add),
      ),
    );
  }

  Widget _buildDashboardTab() {
    return Consumer<TransactionProvider>(
      builder: (context, transactionProvider, child) {
        if (transactionProvider.isLoading && transactionProvider.transactions.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        return RefreshIndicator(
          onRefresh: _refreshData,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Cards resumo
              Row(
                children: [
                  Expanded(
                    child: DashboardCard(
                      title: 'Receitas',
                      value: transactionProvider.totalReceitas,
                      color: AppColors.receita,
                      icon: Icons.trending_up,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: DashboardCard(
                      title: 'Despesas',
                      value: transactionProvider.totalDespesas,
                      color: AppColors.despesa,
                      icon: Icons.trending_down,
                    ),
                  ),
                ],
              ),
              
              const SizedBox(height: 16),
              
              DashboardCard(
                title: 'Lucro',
                value: transactionProvider.lucroTotal,
                color: transactionProvider.lucroTotal >= 0 
                    ? AppColors.receita 
                    : AppColors.despesa,
                icon: Icons.account_balance,
                isLarge: true,
              ),
              
              const SizedBox(height: 24),
              
              // Últimas transações
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Últimas Transações',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  TextButton(
                    onPressed: () {
                      _tabController.animateTo(1);
                    },
                    child: const Text('Ver todas'),
                  ),
                ],
              ),
              
              const SizedBox(height: 8),
              
              ...transactionProvider.transactions
                  .take(5)
                  .map((transaction) => TransactionListItem(
                        transaction: transaction,
                        onTap: () => _editTransaction(transaction.id),
                        onDelete: () => _deleteTransaction(transaction.id),
                      ))
                  .toList(),
            ],
          ),
        );
      },
    );
  }

  Widget _buildTransactionsTab() {
    return Consumer<TransactionProvider>(
      builder: (context, transactionProvider, child) {
        if (transactionProvider.isLoading && transactionProvider.transactions.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        if (transactionProvider.transactions.isEmpty) {
          return const Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.receipt_long, size: 64, color: Colors.grey),
                SizedBox(height: 16),
                Text(
                  'Nenhuma transação encontrada',
                  style: TextStyle(fontSize: 18, color: Colors.grey),
                ),
                SizedBox(height: 8),
                Text(
                  'Adicione sua primeira transação!',
                  style: TextStyle(color: Colors.grey),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: _refreshData,
          child: ListView.builder(
            itemCount: transactionProvider.transactions.length + 
                (transactionProvider.hasMore ? 1 : 0),
            itemBuilder: (context, index) {
              if (index == transactionProvider.transactions.length) {
                // Item de carregamento
                transactionProvider.loadMoreTransactions();
                return const Center(
                  child: Padding(
                    padding: EdgeInsets.all(16.0),
                    child: CircularProgressIndicator(),
                  ),
                );
              }

              final transaction = transactionProvider.transactions[index];
              return TransactionListItem(
                transaction: transaction,
                onTap: () => _editTransaction(transaction.id),
                onDelete: () => _deleteTransaction(transaction.id),
              );
            },
          ),
        );
      },
    );
  }

  Widget _buildReportsTab() {
    return const Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.analytics, size: 64, color: Colors.grey),
          SizedBox(height: 16),
          Text(
            'Relatórios em desenvolvimento',
            style: TextStyle(fontSize: 18, color: Colors.grey),
          ),
          Text(
            'Em breve você terá gráficos e relatórios detalhados',
            style: TextStyle(color: Colors.grey),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  void _showFilters() {
    // TODO: Implementar modal de filtros
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Filtros em desenvolvimento')),
    );
  }

  void _handleMenuAction(String action) {
    switch (action) {
      case 'profile':
        // TODO: Navegar para tela de perfil
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Perfil em desenvolvimento')),
        );
        break;
      case 'subscription':
        // TODO: Navegar para tela de assinatura
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Assinatura em desenvolvimento')),
        );
        break;
      case 'logout':
        _logout();
        break;
    }
  }

  Future<void> _logout() async {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    await authProvider.logout();
    
    if (mounted) {
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  void _editTransaction(int transactionId) {
    Navigator.pushNamed(
      context, 
      '/transaction-form',
      arguments: {'transactionId': transactionId},
    ).then((_) {
      _refreshData();
    });
  }

  Future<void> _deleteTransaction(int transactionId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Excluir Transação'),
        content: const Text('Tem certeza que deseja excluir esta transação?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancelar'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Excluir'),
          ),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      final transactionProvider = Provider.of<TransactionProvider>(context, listen: false);
      final success = await transactionProvider.deleteTransaction(transactionId);
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(success ? 'Transação excluída' : 'Erro ao excluir'),
            backgroundColor: success ? Colors.green : Colors.red,
          ),
        );
      }
    }
  }
}