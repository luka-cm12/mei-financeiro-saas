import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:dio/dio.dart';
import '../services/establishment_service.dart';
import '../models/establishment_model.dart';

class EstablishmentProfileScreen extends StatefulWidget {
  @override
  _EstablishmentProfileScreenState createState() => _EstablishmentProfileScreenState();
}

class _EstablishmentProfileScreenState extends State<EstablishmentProfileScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final _formKey = GlobalKey<FormState>();
  final _establishmentService = EstablishmentService();
  
  // Controllers
  final _businessNameController = TextEditingController();
  final _tradeNameController = TextEditingController();
  final _documentController = TextEditingController();
  final _stateRegistrationController = TextEditingController();
  final _municipalRegistrationController = TextEditingController();
  final _zipCodeController = TextEditingController();
  final _streetController = TextEditingController();
  final _numberController = TextEditingController();
  final _complementController = TextEditingController();
  final _neighborhoodController = TextEditingController();
  final _cityController = TextEditingController();
  final _stateController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _websiteController = TextEditingController();
  final _cnaeController = TextEditingController();
  final _cscController = TextEditingController();
  final _cscIdController = TextEditingController();
  final _certificatePasswordController = TextEditingController();
  
  // State
  EstablishmentModel? _establishment;
  bool _loading = true;
  bool _saving = false;
  String _documentType = 'cpf';
  String _taxRegime = 'mei';
  String _certificateType = 'A1';
  String _nfceEnvironment = 'homologation';
  bool _nfceEnabled = false;
  int _nfceSeries = 1;
  
  // Certificate
  PlatformFile? _selectedCertificate;
  bool _uploadingCertificate = false;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _loadEstablishmentData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    // Dispose controllers
    _businessNameController.dispose();
    _tradeNameController.dispose();
    _documentController.dispose();
    _stateRegistrationController.dispose();
    _municipalRegistrationController.dispose();
    _zipCodeController.dispose();
    _streetController.dispose();
    _numberController.dispose();
    _complementController.dispose();
    _neighborhoodController.dispose();
    _cityController.dispose();
    _stateController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _websiteController.dispose();
    _cnaeController.dispose();
    _cscController.dispose();
    _cscIdController.dispose();
    _certificatePasswordController.dispose();
    super.dispose();
  }

  Future<void> _loadEstablishmentData() async {
    try {
      final establishment = await _establishmentService.getEstablishment();
      if (establishment != null) {
        setState(() {
          _establishment = establishment;
          _populateFields(establishment);
        });
      }
    } catch (e) {
      _showError('Erro ao carregar dados: $e');
    } finally {
      setState(() {
        _loading = false;
      });
    }
  }

  void _populateFields(EstablishmentModel establishment) {
    _businessNameController.text = establishment.businessName;
    _tradeNameController.text = establishment.tradeName ?? '';
    _documentController.text = establishment.document;
    _stateRegistrationController.text = establishment.stateRegistration ?? '';
    _municipalRegistrationController.text = establishment.municipalRegistration ?? '';
    _zipCodeController.text = establishment.zipCode;
    _streetController.text = establishment.street;
    _numberController.text = establishment.number;
    _complementController.text = establishment.complement ?? '';
    _neighborhoodController.text = establishment.neighborhood;
    _cityController.text = establishment.city;
    _stateController.text = establishment.state;
    _phoneController.text = establishment.phone ?? '';
    _emailController.text = establishment.email ?? '';
    _websiteController.text = establishment.website ?? '';
    _cnaeController.text = establishment.cnaeMain ?? '';
    _cscController.text = ''; // Não mostrar CSC por segurança
    _cscIdController.text = ''; // Não mostrar CSC ID por segurança
    
    _documentType = establishment.documentType;
    _taxRegime = establishment.taxRegime;
    _nfceEnabled = establishment.nfceEnabled;
    _nfceEnvironment = establishment.nfceEnvironment;
    _nfceSeries = establishment.nfceSeries;
    _certificateType = establishment.digitalCertificateType ?? 'A1';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Perfil do Estabelecimento'),
        backgroundColor: Colors.blue[600],
        foregroundColor: Colors.white,
        bottom: TabBar(
          controller: _tabController,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          tabs: [
            Tab(icon: Icon(Icons.business), text: 'Dados'),
            Tab(icon: Icon(Icons.security), text: 'Certificado'),
            Tab(icon: Icon(Icons.receipt_long), text: 'NFCe'),
          ],
        ),
      ),
      body: _loading
          ? Center(child: CircularProgressIndicator())
          : TabBarView(
              controller: _tabController,
              children: [
                _buildEstablishmentDataTab(),
                _buildCertificateTab(),
                _buildNFCeTab(),
              ],
            ),
      floatingActionButton: FloatingActionButton(
        onPressed: _saving ? null : _saveEstablishment,
        child: _saving 
            ? SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              )
            : Icon(Icons.save),
        backgroundColor: Colors.green,
      ),
    );
  }

  Widget _buildEstablishmentDataTab() {
    return SingleChildScrollView(
      padding: EdgeInsets.all(16),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Dados da empresa
            Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Dados da Empresa', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    SizedBox(height: 16),
                    
                    TextFormField(
                      controller: _businessNameController,
                      decoration: InputDecoration(
                        labelText: 'Razão Social *',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) => value?.isEmpty ?? true ? 'Campo obrigatório' : null,
                    ),
                    SizedBox(height: 12),
                    
                    TextFormField(
                      controller: _tradeNameController,
                      decoration: InputDecoration(
                        labelText: 'Nome Fantasia',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    SizedBox(height: 12),
                    
                    Row(
                      children: [
                        Expanded(
                          flex: 1,
                          child: DropdownButtonFormField<String>(
                            value: _documentType,
                            decoration: InputDecoration(
                              labelText: 'Tipo',
                              border: OutlineInputBorder(),
                            ),
                            items: [
                              DropdownMenuItem(value: 'cpf', child: Text('CPF')),
                              DropdownMenuItem(value: 'cnpj', child: Text('CNPJ')),
                            ],
                            onChanged: (value) {
                              setState(() {
                                _documentType = value!;
                                _documentController.clear();
                              });
                            },
                          ),
                        ),
                        SizedBox(width: 12),
                        Expanded(
                          flex: 2,
                          child: TextFormField(
                            controller: _documentController,
                            decoration: InputDecoration(
                              labelText: '${_documentType.toUpperCase()} *',
                              border: OutlineInputBorder(),
                            ),
                            validator: (value) => value?.isEmpty ?? true ? 'Campo obrigatório' : null,
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: 12),
                    
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            controller: _stateRegistrationController,
                            decoration: InputDecoration(
                              labelText: 'Inscrição Estadual',
                              border: OutlineInputBorder(),
                            ),
                          ),
                        ),
                        SizedBox(width: 12),
                        Expanded(
                          child: TextFormField(
                            controller: _municipalRegistrationController,
                            decoration: InputDecoration(
                              labelText: 'Inscrição Municipal',
                              border: OutlineInputBorder(),
                            ),
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: 12),
                    
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            value: _taxRegime,
                            decoration: InputDecoration(
                              labelText: 'Regime Tributário',
                              border: OutlineInputBorder(),
                            ),
                            items: [
                              DropdownMenuItem(value: 'mei', child: Text('MEI')),
                              DropdownMenuItem(value: 'simples_nacional', child: Text('Simples Nacional')),
                              DropdownMenuItem(value: 'lucro_presumido', child: Text('Lucro Presumido')),
                              DropdownMenuItem(value: 'lucro_real', child: Text('Lucro Real')),
                            ],
                            onChanged: (value) {
                              setState(() {
                                _taxRegime = value!;
                              });
                            },
                          ),
                        ),
                        SizedBox(width: 12),
                        Expanded(
                          child: TextFormField(
                            controller: _cnaeController,
                            decoration: InputDecoration(
                              labelText: 'CNAE Principal',
                              border: OutlineInputBorder(),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            
            SizedBox(height: 16),
            
            // Endereço
            Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Endereço', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    SizedBox(height: 16),
                    
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            controller: _zipCodeController,
                            decoration: InputDecoration(
                              labelText: 'CEP *',
                              border: OutlineInputBorder(),
                              suffixIcon: IconButton(
                                icon: Icon(Icons.search),
                                onPressed: _searchCep,
                              ),
                            ),
                            validator: (value) => value?.isEmpty ?? true ? 'Campo obrigatório' : null,
                          ),
                        ),
                        SizedBox(width: 12),
                        Expanded(
                          child: TextFormField(
                            controller: _stateController,
                            decoration: InputDecoration(
                              labelText: 'Estado *',
                              border: OutlineInputBorder(),
                            ),
                            validator: (value) => value?.isEmpty ?? true ? 'Campo obrigatório' : null,
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: 12),
                    
                    TextFormField(
                      controller: _streetController,
                      decoration: InputDecoration(
                        labelText: 'Rua/Avenida *',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) => value?.isEmpty ?? true ? 'Campo obrigatório' : null,
                    ),
                    SizedBox(height: 12),
                    
                    Row(
                      children: [
                        Expanded(
                          flex: 1,
                          child: TextFormField(
                            controller: _numberController,
                            decoration: InputDecoration(
                              labelText: 'Número *',
                              border: OutlineInputBorder(),
                            ),
                            validator: (value) => value?.isEmpty ?? true ? 'Campo obrigatório' : null,
                          ),
                        ),
                        SizedBox(width: 12),
                        Expanded(
                          flex: 2,
                          child: TextFormField(
                            controller: _complementController,
                            decoration: InputDecoration(
                              labelText: 'Complemento',
                              border: OutlineInputBorder(),
                            ),
                          ),
                        ),
                      ],
                    ),
                    SizedBox(height: 12),
                    
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            controller: _neighborhoodController,
                            decoration: InputDecoration(
                              labelText: 'Bairro *',
                              border: OutlineInputBorder(),
                            ),
                            validator: (value) => value?.isEmpty ?? true ? 'Campo obrigatório' : null,
                          ),
                        ),
                        SizedBox(width: 12),
                        Expanded(
                          child: TextFormField(
                            controller: _cityController,
                            decoration: InputDecoration(
                              labelText: 'Cidade *',
                              border: OutlineInputBorder(),
                            ),
                            validator: (value) => value?.isEmpty ?? true ? 'Campo obrigatório' : null,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            
            SizedBox(height: 16),
            
            // Contato
            Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Contato', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    SizedBox(height: 16),
                    
                    TextFormField(
                      controller: _phoneController,
                      decoration: InputDecoration(
                        labelText: 'Telefone',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    SizedBox(height: 12),
                    
                    TextFormField(
                      controller: _emailController,
                      decoration: InputDecoration(
                        labelText: 'Email',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    SizedBox(height: 12),
                    
                    TextFormField(
                      controller: _websiteController,
                      decoration: InputDecoration(
                        labelText: 'Website',
                        border: OutlineInputBorder(),
                      ),
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

  Widget _buildCertificateTab() {
    return SingleChildScrollView(
      padding: EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Certificado Digital', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  SizedBox(height: 16),
                  
                  if (_establishment?.certificateUploaded == true) ...[
                    Container(
                      padding: EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.green[50],
                        border: Border.all(color: Colors.green[300]!),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          Icon(Icons.check_circle, color: Colors.green),
                          SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Certificado instalado', style: TextStyle(fontWeight: FontWeight.bold)),
                                if (_establishment?.certificateExpiresAt != null)
                                  Text('Expira em: ${_establishment!.certificateExpiresAt}'),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    SizedBox(height: 16),
                  ],
                  
                  DropdownButtonFormField<String>(
                    value: _certificateType,
                    decoration: InputDecoration(
                      labelText: 'Tipo do Certificado',
                      border: OutlineInputBorder(),
                    ),
                    items: [
                      DropdownMenuItem(value: 'A1', child: Text('A1 (Arquivo)')),
                      DropdownMenuItem(value: 'A3', child: Text('A3 (Token/Smartcard)')),
                    ],
                    onChanged: (value) {
                      setState(() {
                        _certificateType = value!;
                      });
                    },
                  ),
                  SizedBox(height: 16),
                  
                  if (_certificateType == 'A1') ...[
                    Container(
                      padding: EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey[300]!),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Column(
                        children: [
                          Icon(Icons.upload_file, size: 48, color: Colors.grey[600]),
                          SizedBox(height: 8),
                          Text(_selectedCertificate?.name ?? 'Nenhum arquivo selecionado'),
                          SizedBox(height: 12),
                          ElevatedButton.icon(
                            onPressed: _selectCertificate,
                            icon: Icon(Icons.file_present),
                            label: Text('Selecionar Certificado (.pfx/.p12)'),
                          ),
                        ],
                      ),
                    ),
                    SizedBox(height: 16),
                    
                    TextFormField(
                      controller: _certificatePasswordController,
                      decoration: InputDecoration(
                        labelText: 'Senha do Certificado',
                        border: OutlineInputBorder(),
                        suffixIcon: Icon(Icons.lock),
                      ),
                      obscureText: true,
                    ),
                    SizedBox(height: 16),
                    
                    ElevatedButton(
                      onPressed: (_selectedCertificate != null && _certificatePasswordController.text.isNotEmpty && !_uploadingCertificate)
                          ? _uploadCertificate
                          : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        foregroundColor: Colors.white,
                        padding: EdgeInsets.symmetric(vertical: 16),
                      ),
                      child: _uploadingCertificate
                          ? Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                ),
                                SizedBox(width: 12),
                                Text('Enviando...'),
                              ],
                            )
                          : Text('Instalar Certificado', style: TextStyle(fontSize: 16)),
                    ),
                  ] else ...[
                    Container(
                      padding: EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.blue[50],
                        border: Border.all(color: Colors.blue[300]!),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Column(
                        children: [
                          Icon(Icons.info, color: Colors.blue),
                          SizedBox(height: 8),
                          Text(
                            'Para certificado A3 (Token/Smartcard), conecte o dispositivo e certifique-se de que os drivers estão instalados.',
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNFCeTab() {
    return SingleChildScrollView(
      padding: EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Card(
            child: Padding(
              padding: EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Configuração NFCe', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  SizedBox(height: 16),
                  
                  SwitchListTile(
                    title: Text('Habilitar emissão de NFCe'),
                    subtitle: Text('Permite emitir Notas Fiscais de Consumidor Eletrônica'),
                    value: _nfceEnabled,
                    onChanged: (value) {
                      setState(() {
                        _nfceEnabled = value;
                      });
                    },
                  ),
                  
                  if (_nfceEnabled) ...[
                    Divider(),
                    SizedBox(height: 16),
                    
                    DropdownButtonFormField<String>(
                      value: _nfceEnvironment,
                      decoration: InputDecoration(
                        labelText: 'Ambiente',
                        border: OutlineInputBorder(),
                      ),
                      items: [
                        DropdownMenuItem(value: 'homologation', child: Text('Homologação (Teste)')),
                        DropdownMenuItem(value: 'production', child: Text('Produção')),
                      ],
                      onChanged: (value) {
                        setState(() {
                          _nfceEnvironment = value!;
                        });
                      },
                    ),
                    SizedBox(height: 16),
                    
                    TextFormField(
                      initialValue: _nfceSeries.toString(),
                      decoration: InputDecoration(
                        labelText: 'Série da NFCe',
                        border: OutlineInputBorder(),
                        helperText: 'Número da série configurada na SEFAZ',
                      ),
                      keyboardType: TextInputType.number,
                      onChanged: (value) {
                        _nfceSeries = int.tryParse(value) ?? 1;
                      },
                    ),
                    SizedBox(height: 16),
                    
                    TextFormField(
                      controller: _cscIdController,
                      decoration: InputDecoration(
                        labelText: 'ID do CSC',
                        border: OutlineInputBorder(),
                        helperText: 'ID do Código de Segurança do Contribuinte',
                      ),
                    ),
                    SizedBox(height: 16),
                    
                    TextFormField(
                      controller: _cscController,
                      decoration: InputDecoration(
                        labelText: 'CSC (Código de Segurança)',
                        border: OutlineInputBorder(),
                        helperText: 'Código fornecido pela SEFAZ',
                      ),
                      obscureText: true,
                    ),
                    SizedBox(height: 16),
                    
                    Container(
                      padding: EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.orange[50],
                        border: Border.all(color: Colors.orange[300]!),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.warning, color: Colors.orange),
                              SizedBox(width: 8),
                              Text('Importante', style: TextStyle(fontWeight: FontWeight.bold)),
                            ],
                          ),
                          SizedBox(height: 8),
                          Text('• Certifique-se de ter o certificado digital instalado'),
                          Text('• O CSC deve ser obtido no portal da SEFAZ do seu estado'),
                          Text('• Use ambiente de homologação para testes'),
                        ],
                      ),
                    ),
                    SizedBox(height: 16),
                    
                    ElevatedButton(
                      onPressed: _saveNFCeConfig,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.blue,
                        foregroundColor: Colors.white,
                        padding: EdgeInsets.symmetric(vertical: 16),
                      ),
                      child: Text('Salvar Configuração NFCe', style: TextStyle(fontSize: 16)),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _searchCep() async {
    final cep = _zipCodeController.text.replaceAll(RegExp(r'[^0-9]'), '');
    
    if (cep.length != 8) {
      _showError('CEP deve ter 8 dígitos');
      return;
    }

    try {
      final address = await _establishmentService.searchCep(cep);
      if (address != null) {
        setState(() {
          _streetController.text = address['street'] ?? '';
          _neighborhoodController.text = address['neighborhood'] ?? '';
          _cityController.text = address['city'] ?? '';
          _stateController.text = address['state'] ?? '';
        });
      }
    } catch (e) {
      _showError('Erro ao buscar CEP: $e');
    }
  }

  Future<void> _selectCertificate() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pfx', 'p12'],
        allowMultiple: false,
      );

      if (result != null && result.files.isNotEmpty) {
        setState(() {
          _selectedCertificate = result.files.first;
        });
      }
    } catch (e) {
      _showError('Erro ao selecionar arquivo: $e');
    }
  }

  Future<void> _uploadCertificate() async {
    if (_selectedCertificate == null || _certificatePasswordController.text.isEmpty) {
      _showError('Selecione um certificado e informe a senha');
      return;
    }

    setState(() {
      _uploadingCertificate = true;
    });

    try {
      await _establishmentService.uploadCertificate(
        _selectedCertificate!,
        _certificatePasswordController.text,
        _certificateType,
      );

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Certificado instalado com sucesso!'),
          backgroundColor: Colors.green,
        ),
      );

      // Recarregar dados
      _loadEstablishmentData();
      
      // Limpar campos
      setState(() {
        _selectedCertificate = null;
        _certificatePasswordController.clear();
      });

    } catch (e) {
      _showError('Erro ao instalar certificado: $e');
    } finally {
      setState(() {
        _uploadingCertificate = false;
      });
    }
  }

  Future<void> _saveEstablishment() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _saving = true;
    });

    try {
      final data = {
        'business_name': _businessNameController.text,
        'trade_name': _tradeNameController.text,
        'document_type': _documentType,
        'document': _documentController.text,
        'state_registration': _stateRegistrationController.text,
        'municipal_registration': _municipalRegistrationController.text,
        'zip_code': _zipCodeController.text,
        'street': _streetController.text,
        'number': _numberController.text,
        'complement': _complementController.text,
        'neighborhood': _neighborhoodController.text,
        'city': _cityController.text,
        'state': _stateController.text,
        'phone': _phoneController.text,
        'email': _emailController.text,
        'website': _websiteController.text,
        'tax_regime': _taxRegime,
        'cnae_main': _cnaeController.text,
      };

      await _establishmentService.saveEstablishment(data);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Dados salvos com sucesso!'),
          backgroundColor: Colors.green,
        ),
      );

      // Recarregar dados
      _loadEstablishmentData();

    } catch (e) {
      _showError('Erro ao salvar: $e');
    } finally {
      setState(() {
        _saving = false;
      });
    }
  }

  Future<void> _saveNFCeConfig() async {
    try {
      final config = {
        'enabled': _nfceEnabled,
        'environment': _nfceEnvironment,
        'series': _nfceSeries,
        'csc': _cscController.text,
        'csc_id': _cscIdController.text,
      };

      await _establishmentService.configureNFCe(config);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Configuração NFCe salva com sucesso!'),
          backgroundColor: Colors.green,
        ),
      );

    } catch (e) {
      _showError('Erro ao salvar configuração NFCe: $e');
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
      ),
    );
  }
}