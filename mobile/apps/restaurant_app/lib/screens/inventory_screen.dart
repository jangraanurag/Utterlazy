import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class InventoryScreen extends ConsumerStatefulWidget {
  const InventoryScreen({super.key});

  @override
  ConsumerState<InventoryScreen> createState() => _InventoryScreenState();
}

class _InventoryScreenState extends ConsumerState<InventoryScreen> {
  bool _isLoading = true;
  List<dynamic> _items = [];
  List<dynamic> _lowStockItems = [];

  @override
  void initState() {
    super.initState();
    _loadInventory();
  }

  Future<void> _loadInventory() async {
    setState(() => _isLoading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final items = await repo.getInventoryItems();
      final lowStock = await repo.getLowStockItems();
      if (mounted) {
        setState(() {
          _items = items;
          _lowStockItems = lowStock;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  bool _isLowStock(dynamic item) {
    final id = item['id']?.toString() ?? '';
    return _lowStockItems.any((ls) => ls['id']?.toString() == id);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Inventory',
        actions: [
          IconButton(
            icon: const Icon(Icons.add_rounded),
            onPressed: () => _showAddItemDialog(),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _items.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.inventory_2_outlined, size: 64, color: WeloColors.textLight),
                      const SizedBox(height: 12),
                      const Text('No inventory items yet',
                        style: TextStyle(color: WeloColors.textBody)),
                      const SizedBox(height: 16),
                      WeloPrimaryButton(
                        text: 'Add Item',
                        onPressed: () => _showAddItemDialog(),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadInventory,
                  color: WeloColors.sovereignOrange,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _items.length + (_lowStockItems.isNotEmpty ? 1 : 0),
                    itemBuilder: (context, index) {
                      if (index == 0 && _lowStockItems.isNotEmpty) {
                        return _buildLowStockBanner();
                      }
                      final itemIndex = _lowStockItems.isNotEmpty ? index - 1 : index;
                      if (itemIndex >= _items.length) return const SizedBox.shrink();
                      return _buildItemCard(_items[itemIndex]);
                    },
                  ),
                ),
    );
  }

  Widget _buildLowStockBanner() {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: WeloColors.error.withValues(alpha: 0.059),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: WeloColors.error.withValues(alpha: 0.235)),
      ),
      child: Row(
        children: [
          const Icon(Icons.warning_amber_rounded, color: WeloColors.error, size: 22),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              '${_lowStockItems.length} item(s) running low on stock',
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildItemCard(dynamic item) {
    final name = item['name']?.toString() ?? 'Item';
    final stock = double.tryParse((item['stock_quantity'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final unit = item['unit']?.toString() ?? 'kg';
    final low = _isLowStock(item);
    final minStock = double.tryParse((item['low_stock_threshold'] ?? 0)?.toString() ?? '0') ?? 0.0;

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => _showEditDialog(item),
        child: Row(
          children: [
            Container(
              width: 44, height: 44,
              decoration: BoxDecoration(
                color: low ? WeloColors.error.withValues(alpha: 0.078) : WeloColors.success.withValues(alpha: 0.078),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                low ? Icons.warning : Icons.check_circle,
                color: low ? WeloColors.error : WeloColors.success,
                size: 22,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                  const SizedBox(height: 2),
                  Text(
                    'Stock: $stock $unit${minStock > 0 ? '  (min: $minStock)' : ''}',
                    style: TextStyle(
                      color: low ? WeloColors.error : WeloColors.textBody,
                      fontSize: 13,
                      fontWeight: low ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                ],
              ),
            ),
            if (low)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: WeloColors.error.withValues(alpha: 0.078),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text('LOW', style: TextStyle(
                  color: WeloColors.error, fontWeight: FontWeight.bold, fontSize: 11)),
              ),
          ],
        ),
      ),
    );
  }

  void _showAddItemDialog() {
    final nameCtrl = TextEditingController();
    final stockCtrl = TextEditingController();
    final costCtrl = TextEditingController();
    final minCtrl = TextEditingController();
    String selectedUnit = 'kg';

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
            const Text('Add Inventory Item', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            WeloTextField(label: 'Item Name', controller: nameCtrl, hintText: 'e.g., Chicken'),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: WeloTextField(label: 'Current Stock', controller: stockCtrl,
                    hintText: '0', keyboardType: TextInputType.number),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: selectedUnit,
                    decoration: InputDecoration(
                      labelText: 'Unit',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                    ),
                    items: ['kg', 'g', 'litre', 'ml', 'pcs', 'packet'].map((u) =>
                      DropdownMenuItem(value: u, child: Text(u))).toList(),
                    onChanged: (v) => selectedUnit = v ?? 'kg',
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: WeloTextField(label: 'Min Stock Alert', controller: minCtrl,
                    hintText: '0', keyboardType: TextInputType.number),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: WeloTextField(label: 'Cost per Unit', controller: costCtrl,
                    hintText: '0', keyboardType: TextInputType.number),
                ),
              ],
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: WeloPrimaryButton(
                text: 'Add Item',
                onPressed: () async {
                  if (nameCtrl.text.isEmpty) return;
                  final data = {
                    'name': nameCtrl.text,
                    'unit': selectedUnit,
                    'stock_quantity': double.tryParse(stockCtrl.text) ?? 0,
                    'low_stock_threshold': double.tryParse(minCtrl.text) ?? 0,
                    'cost_per_unit': double.tryParse(costCtrl.text) ?? 0,
                  };
                  try {
                    await ref.read(vendorRepositoryProvider).addInventoryItem(data);
                    if (!mounted) return;
                    Navigator.pop(ctx);
                    _loadInventory();
                  } catch (e) {
                    if (ctx.mounted) {
                      ScaffoldMessenger.of(ctx).showSnackBar(
                        SnackBar(content: Text('Failed to add: $e')));
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

  void _showEditDialog(dynamic item) {
    final id = item['id']?.toString() ?? '';
    final stockCtrl = TextEditingController(text: (item['stock_quantity'] ?? 0).toString());
    final minCtrl = TextEditingController(text: (item['low_stock_threshold'] ?? 0).toString());
    final costCtrl = TextEditingController(text: (item['cost_per_unit'] ?? 0).toString());
    String selectedUnit = item['unit']?.toString() ?? 'kg';
    bool isAvailable = item['is_available'] ?? true;

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
            Text('Edit: ${item['name']}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: WeloTextField(label: 'Current Stock', controller: stockCtrl,
                    keyboardType: TextInputType.number),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DropdownButtonFormField<String>(
                    initialValue: selectedUnit,
                    decoration: InputDecoration(
                      labelText: 'Unit',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                    ),
                    items: ['kg', 'g', 'litre', 'ml', 'pcs', 'packet'].map((u) =>
                      DropdownMenuItem(value: u, child: Text(u))).toList(),
                    onChanged: (v) => selectedUnit = v ?? 'kg',
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: WeloTextField(label: 'Min Stock Alert', controller: minCtrl,
                    keyboardType: TextInputType.number),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: WeloTextField(label: 'Cost per Unit', controller: costCtrl,
                    keyboardType: TextInputType.number),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                const Text('Available'),
                const Spacer(),
                Switch(
                  value: isAvailable,
                  activeThumbColor: WeloColors.success,
                  onChanged: (v) => isAvailable = v,
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
                    'stock_quantity': double.tryParse(stockCtrl.text) ?? 0,
                    'unit': selectedUnit,
                    'low_stock_threshold': double.tryParse(minCtrl.text) ?? 0,
                    'cost_per_unit': double.tryParse(costCtrl.text) ?? 0,
                    'is_available': isAvailable,
                  };
                  try {
                    await ref.read(vendorRepositoryProvider).updateInventoryItem(id, data);
                    if (!mounted) return;
                    Navigator.pop(ctx);
                    _loadInventory();
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
}
