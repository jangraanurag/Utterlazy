import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class BogoScreen extends ConsumerStatefulWidget {
  const BogoScreen({super.key});

  @override
  ConsumerState<BogoScreen> createState() => _BogoScreenState();
}

class _BogoScreenState extends ConsumerState<BogoScreen> {
  List<dynamic> _bogos = [];
  List<dynamic> _menuItems = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final bogos = await repo.getBogoDiscounts();
      final menu = await repo.getMenu();
      final items = <dynamic>[];
      final cats = menu['categories'] as List<dynamic>? ?? [];
      for (final c in cats) {
        items.addAll(c['menu_items'] as List<dynamic>? ?? []);
      }
      items.addAll(menu['uncategorized'] as List<dynamic>? ?? []);
      if (mounted) {
        setState(() { _bogos = bogos; _menuItems = items; _isLoading = false; });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'BOGO Discounts',
        actions: [
          IconButton(icon: const Icon(Icons.add_rounded, size: 24),
            onPressed: () => _showBogoForm()),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _bogos.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.local_offer_outlined, size: 64, color: WeloColors.textLight),
                      const SizedBox(height: 16),
                      const Text('No BOGO discounts yet'),
                      const SizedBox(height: 8),
                      ElevatedButton.icon(onPressed: () => _showBogoForm(),
                        icon: const Icon(Icons.add), label: const Text('Create BOGO')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadData,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _bogos.length,
                    itemBuilder: (context, index) => _buildBogoCard(_bogos[index]),
                  ),
                ),
    );
  }

  Widget _buildBogoCard(dynamic bogo) {
    final id = bogo['id'].toString();
    final title = bogo['title'] ?? '';
    final buyQty = bogo['buy_quantity'] ?? 1;
    final getQty = bogo['get_quantity'] ?? 1;
    final isActive = bogo['is_active'] == true || bogo['is_active'] == 1;
    final items = bogo['menu_items'] as List<dynamic>? ?? [];

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: Colors.green, borderRadius: BorderRadius.circular(8)),
                  child: const Text('BOGO', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                ),
                const SizedBox(width: 12),
                Expanded(child: Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15))),
                Switch(value: isActive, activeThumbColor: Colors.green, onChanged: (v) async {
                  try { await ref.read(vendorRepositoryProvider).toggleBogo(id); _loadData(); }
                  catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e'))); }
                }),
              ],
            ),
            const SizedBox(height: 8),
            Text('Buy $buyQty Get $getQty Free', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Colors.green[700])),
            if (items.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text('${items.length} item(s) eligible', style: const TextStyle(color: Colors.black54, fontSize: 12)),
            ],
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton.icon(
                  icon: const Icon(Icons.edit_outlined, size: 16),
                  label: const Text('Edit', style: TextStyle(fontSize: 12)),
                  onPressed: () => _showBogoForm(bogo: bogo),
                ),
                TextButton.icon(
                  icon: const Icon(Icons.delete_outline, size: 16, color: Colors.red),
                  label: const Text('Delete', style: TextStyle(fontSize: 12, color: Colors.red)),
                  onPressed: () async {
                    final confirm = await showDialog<bool>(context: context,
                      builder: (ctx) => AlertDialog(
                        title: const Text('Delete BOGO'),
                        content: Text('Delete "$title"?'),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
                          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete', style: TextStyle(color: Colors.red))),
                        ],
                      ));
                    if (confirm == true) {
                      try { await ref.read(vendorRepositoryProvider).deleteBogo(id); _loadData(); }
                      catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e'))); }
                    }
                  },
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _showBogoForm({dynamic bogo}) {
    final isEdit = bogo != null;
    final titleCtrl = TextEditingController(text: bogo?['title'] ?? '');
    final buyCtrl = TextEditingController(text: (bogo?['buy_quantity'] ?? 1).toString());
    final getCtrl = TextEditingController(text: (bogo?['get_quantity'] ?? 1).toString());
    final startCtrl = TextEditingController(text: (bogo?['start_date'] ?? '').toString().substring(0, 10));
    final endCtrl = TextEditingController(text: bogo?['end_date'] != null ? bogo['end_date'].toString().substring(0, 10) : '');
    bool allDay = bogo?['all_day'] == true || bogo?['all_day'] == 1;
    String timeFrom = bogo?['time_from']?.toString() ?? '';
    String timeTo = bogo?['time_to']?.toString() ?? '';
    String userType = bogo?['user_type'] ?? 'all';

    final existingIds = (bogo?['menu_items'] as List<dynamic>?)
            ?.map((e) => e['id']?.toString() ?? '')
            .where((id) => id.isNotEmpty).toSet() ??
        <String>{};
    final selectedIds = existingIds.toSet();

    bool isLoading = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => SizedBox(
          height: MediaQuery.of(ctx).size.height * 0.85,
          child: Padding(
            padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(ctx).viewInsets.bottom + 24),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(isEdit ? 'Edit BOGO' : 'New BOGO Discount',
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 16),
                  TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: 'Title', hintText: 'e.g., Buy 1 Get 1 Free', border: OutlineInputBorder())),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(child: TextField(controller: buyCtrl, decoration: const InputDecoration(labelText: 'Buy Qty', border: OutlineInputBorder()), keyboardType: TextInputType.number)),
                      const SizedBox(width: 12),
                      Expanded(child: TextField(controller: getCtrl, decoration: const InputDecoration(labelText: 'Free Qty', border: OutlineInputBorder()), keyboardType: TextInputType.number)),
                    ],
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: userType,
                    decoration: const InputDecoration(labelText: 'User Type', border: OutlineInputBorder()),
                    items: const [
                      DropdownMenuItem(value: 'all', child: Text('All Users')),
                      DropdownMenuItem(value: 'new_users', child: Text('New Users Only')),
                    ],
                    onChanged: (v) => setSheetState(() => userType = v ?? 'all'),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(child: TextField(controller: startCtrl, decoration: const InputDecoration(labelText: 'Start Date', hintText: 'YYYY-MM-DD', border: OutlineInputBorder()))),
                      const SizedBox(width: 12),
                      Expanded(child: TextField(controller: endCtrl, decoration: const InputDecoration(labelText: 'End Date (optional)', hintText: 'YYYY-MM-DD', border: OutlineInputBorder()))),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      const Text('All Day'),
                      Switch(value: allDay, onChanged: (v) => setSheetState(() => allDay = v)),
                      if (!allDay) ...[
                        const SizedBox(width: 12),
                        Expanded(child: TextField(decoration: const InputDecoration(labelText: 'From', hintText: '09:00', border: OutlineInputBorder()), onChanged: (v) => timeFrom = v)),
                        const SizedBox(width: 8),
                        Expanded(child: TextField(decoration: const InputDecoration(labelText: 'To', hintText: '18:00', border: OutlineInputBorder()), onChanged: (v) => timeTo = v)),
                      ],
                    ],
                  ),
                  const SizedBox(height: 16),
                  const Text('Select Menu Items', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  const SizedBox(height: 8),
                  if (_menuItems.isEmpty)
                    const Text('No menu items available', style: TextStyle(color: Colors.grey))
                  else
                    ..._menuItems.map((mi) {
                      final miId = mi['id']?.toString() ?? '';
                      final miName = mi['name']?.toString() ?? '';
                      return CheckboxListTile(
                        dense: true,
                        title: Text(miName, style: const TextStyle(fontSize: 13)),
                        value: selectedIds.contains(miId),
                        onChanged: (v) {
                          setSheetState(() { if (v == true) selectedIds.add(miId); else selectedIds.remove(miId); });
                        },
                        controlAffinity: ListTileControlAffinity.leading,
                      );
                    }),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.black, foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      onPressed: isLoading ? null : () async {
                        if (titleCtrl.text.isEmpty || selectedIds.isEmpty) {
                          ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Title and at least one item required')));
                          return;
                        }
                        setSheetState(() => isLoading = true);
                        final data = <String, dynamic>{
                          'title': titleCtrl.text,
                          'buy_quantity': int.tryParse(buyCtrl.text) ?? 1,
                          'get_quantity': int.tryParse(getCtrl.text) ?? 1,
                          'user_type': userType,
                          'all_day': allDay,
                          'start_date': startCtrl.text,
                          'end_date': endCtrl.text.isEmpty ? null : endCtrl.text,
                          'menu_item_ids': selectedIds.toList(),
                        };
                        if (!allDay) {
                          data['time_from'] = timeFrom.isEmpty ? null : timeFrom;
                          data['time_to'] = timeTo.isEmpty ? null : timeTo;
                        }
                        try {
                          final repo = ref.read(vendorRepositoryProvider);
                          if (isEdit) { await repo.updateBogo(bogo['id'].toString(), data); }
                          else { await repo.createBogo(data); }
                          if (ctx.mounted) Navigator.pop(ctx);
                          _loadData();
                        } catch (e) {
                          setSheetState(() => isLoading = false);
                          if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('Failed: $e')));
                        }
                      },
                      child: Text(isLoading ? 'Saving...' : (isEdit ? 'Update BOGO' : 'Create BOGO'),
                        style: const TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
