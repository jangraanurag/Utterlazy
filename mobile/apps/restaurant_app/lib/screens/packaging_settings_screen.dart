import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class PackagingSettingsScreen extends ConsumerStatefulWidget {
  const PackagingSettingsScreen({super.key});

  @override
  ConsumerState<PackagingSettingsScreen> createState() => _PackagingSettingsScreenState();
}

class _PackagingSettingsScreenState extends ConsumerState<PackagingSettingsScreen> {
  String chargeType = 'fixed';
  String chargeValue = '';
  String taxType = 'NONE';
  bool isActive = false;
  bool _isLoading = true;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final settings = await ref.read(vendorRepositoryProvider).getPackagingSettings();
      if (settings.isNotEmpty) {
        setState(() {
          chargeType = settings['charge_type'] ?? 'fixed';
          chargeValue = (settings['charge_value'] ?? '0').toString();
          taxType = settings['tax_type'] ?? 'NONE';
          isActive = settings['is_active'] == true || settings['is_active'] == 1;
          _isLoading = false;
        });
      } else {
        if (mounted) setState(() => _isLoading = false);
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Packaging Settings'),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SwitchListTile(
                    title: const Text('Enable Packaging Charge', style: TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: const Text('Applies to all orders when no item-level override exists'),
                    value: isActive,
                    onChanged: (v) => setState(() => isActive = v),
                  ),
                  const SizedBox(height: 24),
                  if (isActive) ...[
                    const Text('Charge Type', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: ChoiceChip(
                            label: const Text('Fixed (₹)', style: TextStyle(fontSize: 12)),
                            selected: chargeType == 'fixed',
                            onSelected: (v) => setState(() => chargeType = 'fixed'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ChoiceChip(
                            label: const Text('Percentage (%)', style: TextStyle(fontSize: 12)),
                            selected: chargeType == 'percentage',
                            onSelected: (v) => setState(() => chargeType = 'percentage'),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      decoration: InputDecoration(
                        labelText: chargeType == 'fixed' ? 'Charge Amount (₹)' : 'Charge Percentage (%)',
                        hintText: chargeType == 'fixed' ? 'e.g., 20' : 'e.g., 5',
                        border: const OutlineInputBorder(),
                      ),
                      keyboardType: TextInputType.number,
                      controller: TextEditingController(text: chargeValue),
                      onChanged: (v) => setState(() => chargeValue = v),
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      initialValue: taxType,
                      decoration: const InputDecoration(
                        labelText: 'Tax on Packaging Charge',
                        border: OutlineInputBorder(),
                      ),
                      items: const [
                        DropdownMenuItem(value: 'NONE', child: Text('No Tax')),
                        DropdownMenuItem(value: 'GST_5', child: Text('GST 5%')),
                      ],
                      onChanged: (v) => setState(() => taxType = v ?? 'NONE'),
                    ),
                  ],
                  const SizedBox(height: 32),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.black,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      onPressed: _isSaving ? null : () async {
                        setState(() => _isSaving = true);
                        try {
                          await ref.read(vendorRepositoryProvider).savePackagingSettings({
                            'charge_type': chargeType,
                            'charge_value': double.tryParse(chargeValue) ?? 0,
                            'tax_type': taxType,
                            'is_active': isActive,
                          });
                          if (!mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Settings saved'), backgroundColor: Colors.green));
                        } catch (e) {
                          if (!mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red));
                        } finally {
                          if (mounted) setState(() => _isSaving = false);
                        }
                      },
                      child: Text(_isSaving ? 'Saving...' : 'Save Settings',
                        style: const TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
