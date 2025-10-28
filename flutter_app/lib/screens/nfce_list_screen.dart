import 'package:flutter/material.dart';
import '../models/nfce_model.dart';
import '../services/nfce_service.dart';
import '../widgets/loading_widget.dart';
import '../widgets/error_widget.dart';
import 'nfce_details_screen.dart';
import 'nfce_emission_screen.dart';

class NFCeListScreen extends StatefulWidget {
  const NFCeListScreen({Key? key}) : super(key: key);

  @override
  State<NFCeListScreen> createState() => _NFCeListScreenState();
}

class _NFCeListScreenState extends State<NFCeListScreen>
    with SingleTickerProviderStateMixin {
  final _nfceService = NFCeService();
  late TabController _tabController;
  
  List<NFCe> _nfces = [];
  NFCeStatistics? _statistics;
  bool _isLoading = true;
  String? _error;
  
  // Filtros
  String _selectedStatus = 'all';
  String _selectedPeriod = 'month';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    try {
      setState(() {
        _isLoading = true;
        _error = null;
      });

      // Carregar NFCes e estatísticas em paralelo
      final futures = await Future.wait([
        _loadNFCes(),
        _loadStatistics(),
      ]);

      setState(() {
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString().replaceAll('Exception: ', '');
        _isLoading = false;
      });
    }
  }

  Future<void> _loadNFCes() async {
    final nfces = await _nfceService.getNFCes(
      status: _selectedStatus != 'all' ? _selectedStatus : null,
    );
    
    setState(() {
      _nfces = nfces;
    });
  }

  Future<void> _loadStatistics() async {
    final statistics = await _nfceService.getStatistics(period: _selectedPeriod);
    
    setState(() {
      _statistics = statistics;
    });
  }

  void _showFilterDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Filtros'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Status:', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              value: _selectedStatus,
              decoration: const InputDecoration(
                border: OutlineInputBorder(),
              ),
              items: _nfceService.getNFCeStatusOptions().entries.map((entry) {
                return DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value),
                );
              }).toList(),
              onChanged: (value) {
                setState(() {
                  _selectedStatus = value!;
                });
              },
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
              Navigator.pop(context);
              _loadNFCes();
            },
            child: const Text('Aplicar'),
          ),
        ],
      ),
    );
  }

  void _goToEmission() async {
    final result = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (context) => const NFCeEmissionScreen(),
      ),
    );

    if (result == true) {
      _loadData();
    }
  }

  void _viewNFCeDetails(NFCe nfce) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => NFCeDetailsScreen(nfceId: nfce.id!),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('NFCe'),
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        bottom: TabBar(
          controller: _tabController,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(text: 'NFCes', icon: Icon(Icons.receipt_long)),
            Tab(text: 'Estatísticas', icon: Icon(Icons.analytics)),
          ],
        ),
        actions: [
          IconButton(
            onPressed: _showFilterDialog,
            icon: const Icon(Icons.filter_list),
            tooltip: 'Filtros',
          ),
          IconButton(
            onPressed: _loadData,
            icon: const Icon(Icons.refresh),
            tooltip: 'Atualizar',
          ),
        ],
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildNFCesList(),
          _buildStatistics(),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _goToEmission,
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Nova NFCe'),
      ),
    );
  }

  Widget _buildNFCesList() {
    if (_isLoading) {
      return const LoadingWidget(message: 'Carregando NFCes...');
    }

    if (_error != null) {
      return ErrorWidgetCustom(
        message: _error!,
        onRetry: _loadData,
      );
    }

    if (_nfces.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.receipt_long_outlined,
              size: 64,
              color: Colors.grey.shade400,
            ),
            const SizedBox(height: 16),
            Text(
              'Nenhuma NFCe encontrada',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey.shade600,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Emita sua primeira NFCe',
              style: TextStyle(
                color: Colors.grey.shade500,
              ),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _goToEmission,
              icon: const Icon(Icons.add),
              label: const Text('Nova NFCe'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadData,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _nfces.length,
        itemBuilder: (context, index) {
          final nfce = _nfces[index];
          
          return Card(
            margin: const EdgeInsets.only(bottom: 12),
            child: InkWell(
              onTap: () => _viewNFCeDetails(nfce),
              borderRadius: BorderRadius.circular(8),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'NFCe ${nfce.nfceNumber}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 16,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                nfce.formattedEmissionDateTime,
                                style: TextStyle(
                                  color: Colors.grey.shade600,
                                  fontSize: 14,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              nfce.formattedTotalAmount,
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                                color: Colors.green,
                              ),
                            ),
                            const SizedBox(height: 4),
                            _buildStatusChip(nfce.status),
                          ],
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Icon(
                          Icons.payment,
                          size: 16,
                          color: Colors.grey.shade600,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          nfce.paymentMethodDisplay,
                          style: TextStyle(
                            color: Colors.grey.shade600,
                            fontSize: 14,
                          ),
                        ),
                        const SizedBox(width: 16),
                        Icon(
                          Icons.inventory_2,
                          size: 16,
                          color: Colors.grey.shade600,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${nfce.items.length} ${nfce.items.length == 1 ? 'item' : 'itens'}',
                          style: TextStyle(
                            color: Colors.grey.shade600,
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                    if (nfce.customerName != null && nfce.customerName!.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Icon(
                            Icons.person,
                            size: 16,
                            color: Colors.grey.shade600,
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              nfce.customerName!,
                              style: TextStyle(
                                color: Colors.grey.shade600,
                                fontSize: 14,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildStatistics() {
    if (_isLoading) {
      return const LoadingWidget(message: 'Carregando estatísticas...');
    }

    if (_error != null) {
      return ErrorWidgetCustom(
        message: _error!,
        onRetry: _loadData,
      );
    }

    if (_statistics == null) {
      return const Center(
        child: Text('Nenhuma estatística disponível'),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadData,
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Selector de período
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Período',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(value: 'today', label: Text('Hoje')),
                        ButtonSegment(value: 'week', label: Text('Semana')),
                        ButtonSegment(value: 'month', label: Text('Mês')),
                        ButtonSegment(value: 'year', label: Text('Ano')),
                      ],
                      selected: {_selectedPeriod},
                      onSelectionChanged: (selection) {
                        setState(() {
                          _selectedPeriod = selection.first;
                        });
                        _loadStatistics();
                      },
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            
            // Cards de estatísticas
            Row(
              children: [
                Expanded(
                  child: _buildStatCard(
                    'Total de NFCes',
                    _statistics!.totalNfces.toString(),
                    Icons.receipt_long,
                    Colors.blue,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: _buildStatCard(
                    'Autorizadas',
                    _statistics!.authorizedCount.toString(),
                    Icons.check_circle,
                    Colors.green,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            
            Row(
              children: [
                Expanded(
                  child: _buildStatCard(
                    'Canceladas',
                    _statistics!.cancelledCount.toString(),
                    Icons.cancel,
                    Colors.red,
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: _buildStatCard(
                    'Pendentes',
                    _statistics!.pendingCount.toString(),
                    Icons.pending,
                    Colors.orange,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            
            // Valores
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Valores',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Icon(Icons.attach_money, color: Colors.green.shade600),
                        const SizedBox(width: 8),
                        const Text('Receita Total: '),
                        Text(
                          _statistics!.formattedTotalRevenue,
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Colors.green,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Icon(Icons.trending_up, color: Colors.blue.shade600),
                        const SizedBox(width: 8),
                        const Text('Ticket Médio: '),
                        Text(
                          _statistics!.formattedAverageTicket,
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Colors.blue,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Icon(Icons.percent, color: Colors.purple.shade600),
                        const SizedBox(width: 8),
                        const Text('Taxa de Sucesso: '),
                        Text(
                          _statistics!.formattedSuccessRate,
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Colors.purple,
                          ),
                        ),
                      ],
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

  Widget _buildStatCard(String title, String value, IconData icon, Color color) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, color: color, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey.shade600,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              value,
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatusChip(String status) {
    Color color;
    String label;

    switch (status) {
      case 'authorized':
        color = Colors.green;
        label = 'Autorizada';
        break;
      case 'pending':
        color = Colors.orange;
        label = 'Pendente';
        break;
      case 'cancelled':
        color = Colors.red;
        label = 'Cancelada';
        break;
      case 'rejected':
        color = Colors.red;
        label = 'Rejeitada';
        break;
      case 'generated':
        color = Colors.blue;
        label = 'Gerada';
        break;
      case 'error':
        color = Colors.red;
        label = 'Erro';
        break;
      default:
        color = Colors.grey;
        label = 'Desconhecido';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        border: Border.all(color: color.withOpacity(0.3)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w500,
        ),
      ),
    );
  }
}