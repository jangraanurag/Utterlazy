import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class ComboOffersScreen extends ConsumerStatefulWidget {
  const ComboOffersScreen({super.key});

  @override
  ConsumerState<ComboOffersScreen> createState() => _ComboOffersScreenState();
}

class _ComboOffersScreenState extends ConsumerState<ComboOffersScreen> {
  bool _isLoading = true;
  List<dynamic> _combos = [];

  @override
  void initState() {
    super.initState();
    _loadCombos();
  }

  Future<void> _loadCombos() async {
    setState(() => _isLoading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final combos = await repo.getComboOffers();
      if (mounted) setState(() { _combos = combos; _isLoading = false; });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _toggleCombo(String id, bool isActive) async {
    try {
      await ref.read(vendorRepositoryProvider).updateComboOffer(id, {'is_active': isActive});
      _loadCombos();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update: $e')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Combo Offers',
        actions: [
          IconButton(
            icon: const Icon(Icons.add_rounded),
            onPressed: () => _showAddComboDialog(),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _combos.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.local_offer_outlined, size: 64, color: WeloColors.textLight),
                      const SizedBox(height: 12),
                      const Text('No combo offers yet',
                        style: TextStyle(color: WeloColors.textBody)),
                      const SizedBox(height: 16),
                      WeloPrimaryButton(
                        text: 'Create Combo',
                        onPressed: () => _showAddComboDialog(),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadCombos,
                  color: WeloColors.sovereignOrange,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _combos.length,
                    itemBuilder: (context, index) => _buildComboCard(_combos[index]),
                  ),
                ),
    );
  }

  Widget _buildComboCard(dynamic combo) {
    final name = combo['name']?.toString() ?? 'Combo';
    final description = combo['description']?.toString() ?? '';
    final comboPrice = double.tryParse((combo['combo_price'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final originalPrice = double.tryParse((combo['original_price'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final isActive = combo['is_active'] == true || combo['is_active'] == 1;
    final id = combo['id']?.toString() ?? '';
    final items = combo['items'] as List<dynamic>? ?? [];
    final discount = originalPrice > 0 ? ((1 - comboPrice / originalPrice) * 100).round() : 0;

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              ),
              Switch(
                value: isActive,
                activeThumbColor: WeloColors.success,
                onChanged: (v) => _toggleCombo(id, v),
              ),
            ],
          ),
          if (description.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(description, style: const TextStyle(color: WeloColors.textBody, fontSize: 13)),
          ],
          if (items.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(items.map((i) => i is Map ? (i['name'] ?? i['item'] ?? '') : i.toString()).join(', '),
              style: const TextStyle(color: WeloColors.textBody, fontSize: 12)),
          ],
          const SizedBox(height: 8),
          Row(
            children: [
              Text('₹${comboPrice.toInt()}', style: const TextStyle(
                fontWeight: FontWeight.bold, fontSize: 18, color: WeloColors.sovereignOrange)),
              const SizedBox(width: 8),
              Text('₹${originalPrice.toInt()}', style: const TextStyle(
                decoration: TextDecoration.lineThrough, color: WeloColors.textLight)),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(color: WeloColors.success.withValues(alpha: 0.078), borderRadius: BorderRadius.circular(6)),
                child: Text('$discount% OFF', style: const TextStyle(
                  fontSize: 10, fontWeight: FontWeight.bold, color: WeloColors.success)),
              ),
              const Spacer(),
              PopupMenuButton<String>(
                onSelected: (v) {
                  if (v == 'edit') _showEditComboDialog(combo);
                  if (v == 'delete') _confirmDelete(combo);
                },
                itemBuilder: (_) => [
                  const PopupMenuItem(value: 'edit', child: Text('Edit')),
                  const PopupMenuItem(value: 'delete', child: Text('Delete')),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _showAddComboDialog() {
    final nameCtrl = TextEditingController();
    final descCtrl = TextEditingController();
    final comboPriceCtrl = TextEditingController();
    final originalPriceCtrl = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(ctx).viewInsets.bottom + 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Create Combo Offer', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            WeloTextField(label: 'Combo Name', controller: nameCtrl, hintText: 'e.g., Family Feast'),
            const SizedBox(height: 12),
            WeloTextField(label: 'Description', controller: descCtrl, hintText: 'What\'s included'),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: WeloTextField(label: 'Combo Price (₹)', controller: comboPriceCtrl,
                    hintText: '299', keyboardType: TextInputType.number),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: WeloTextField(label: 'Original Price (₹)', controller: originalPriceCtrl,
                    hintText: '499', keyboardType: TextInputType.number),
                ),
              ],
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: WeloPrimaryButton(
                text: 'Create Combo',
                onPressed: () async {
                  if (nameCtrl.text.isEmpty) return;
                  final data = {
                    'name': nameCtrl.text,
                    'description': descCtrl.text,
                    'items': [],
                    'combo_price': double.tryParse(comboPriceCtrl.text) ?? 0,
                    'original_price': double.tryParse(originalPriceCtrl.text) ?? 0,
                  };
                  try {
                    await ref.read(vendorRepositoryProvider).addComboOffer(data);
                    if (!mounted) return;
                    Navigator.pop(ctx);
                    _loadCombos();
                  } catch (e) {
                    if (ctx.mounted) {
                      ScaffoldMessenger.of(ctx).showSnackBar(
                        SnackBar(content: Text('Failed to create: $e')));
                    }
                  }
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showEditComboDialog(dynamic combo) {
    final id = combo['id']?.toString() ?? '';
    final nameCtrl = TextEditingController(text: combo['name']?.toString() ?? '');
    final descCtrl = TextEditingController(text: combo['description']?.toString() ?? '');
    final comboPriceCtrl = TextEditingController(text: (combo['combo_price'] ?? 0).toString());
    final originalPriceCtrl = TextEditingController(text: (combo['original_price'] ?? 0).toString());

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(ctx).viewInsets.bottom + 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Edit Combo', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            WeloTextField(label: 'Combo Name', controller: nameCtrl),
            const SizedBox(height: 12),
            WeloTextField(label: 'Description', controller: descCtrl),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: WeloTextField(label: 'Combo Price (₹)', controller: comboPriceCtrl,
                    keyboardType: TextInputType.number),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: WeloTextField(label: 'Original Price (₹)', controller: originalPriceCtrl,
                    keyboardType: TextInputType.number),
                ),
              ],
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: WeloPrimaryButton(
                text: 'Save',
                onPressed: () async {
                  final data = {
                    'name': nameCtrl.text,
                    'description': descCtrl.text,
                    'combo_price': double.tryParse(comboPriceCtrl.text) ?? 0,
                    'original_price': double.tryParse(originalPriceCtrl.text) ?? 0,
                  };
                  try {
                    await ref.read(vendorRepositoryProvider).updateComboOffer(id, data);
                    if (!mounted) return;
                    Navigator.pop(ctx);
                    _loadCombos();
                  } catch (e) {
                    if (ctx.mounted) {
                      ScaffoldMessenger.of(ctx).showSnackBar(
                        SnackBar(content: Text('Failed to update: $e')));
                    }
                  }
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _confirmDelete(dynamic combo) {
    final id = combo['id']?.toString() ?? '';
    final name = combo['name']?.toString() ?? '';

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Combo'),
        content: Text('Delete "$name"?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () async {
              try {
                await ref.read(vendorRepositoryProvider).removeComboOffer(id);
                if (!mounted) return;
                Navigator.pop(ctx);
                _loadCombos();
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(
                    SnackBar(content: Text('Failed to delete: $e')));
                }
              }
            },
            child: const Text('Delete', style: TextStyle(color: WeloColors.error)),
          ),
        ],
      ),
    );
  }
}
