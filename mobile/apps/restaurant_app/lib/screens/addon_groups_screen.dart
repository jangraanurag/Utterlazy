import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class AddOnGroupsScreen extends ConsumerStatefulWidget {
  const AddOnGroupsScreen({super.key});

  @override
  ConsumerState<AddOnGroupsScreen> createState() => _AddOnGroupsScreenState();
}

class _AddOnGroupsScreenState extends ConsumerState<AddOnGroupsScreen> {
  List<dynamic> _groups = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadGroups();
  }

  Future<void> _loadGroups() async {
    setState(() => _isLoading = true);
    try {
      final groups = await ref.read(vendorRepositoryProvider).getAddOnGroups();
      if (mounted) setState(() { _groups = groups; _isLoading = false; });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Add-on Groups',
        actions: [
          IconButton(
            icon: const Icon(Icons.add_rounded, size: 24),
            onPressed: () => _showAddEditGroupDialog(),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _groups.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.inventory_2_outlined, size: 64, color: WeloColors.textLight),
                      const SizedBox(height: 16),
                      const Text('No add-on groups yet', style: TextStyle(color: WeloColors.textBody)),
                      const SizedBox(height: 8),
                      ElevatedButton.icon(
                        onPressed: () => _showAddEditGroupDialog(),
                        icon: const Icon(Icons.add, size: 18),
                        label: const Text('Create Group'),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadGroups,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _groups.length,
                    itemBuilder: (context, index) => _buildGroupCard(_groups[index]),
                  ),
                ),
    );
  }

  Widget _buildGroupCard(dynamic group) {
    final id = group['id']?.toString() ?? '';
    final name = group['name']?.toString() ?? '';
    final selectionType = group['selection_type']?.toString() ?? 'multiple';
    final items = group['items'] as List<dynamic>? ?? [];

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: ExpansionTile(
        title: Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
        subtitle: Text('${items.length} items · ${selectionType == "single" ? "Select one" : "Select multiple"}',
          style: const TextStyle(fontSize: 12)),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        children: [
          ...items.map((item) => ListTile(
            dense: true,
            title: Text(item['name']?.toString() ?? '', style: const TextStyle(fontSize: 13)),
            trailing: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text('₹${double.tryParse(item['price']?.toString() ?? '0')?.toInt() ?? 0}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                const SizedBox(width: 8),
                InkWell(
                  onTap: () => _showAddEditItemDialog(group, item: item),
                  child: const Icon(Icons.edit_outlined, size: 16, color: WeloColors.textBody),
                ),
                const SizedBox(width: 4),
                InkWell(
                  onTap: () async {
                    final confirmed = await showDialog<bool>(
                      context: context,
                      builder: (ctx) => AlertDialog(
                        title: const Text('Delete Item'),
                        content: Text('Delete "${item['name']}"?'),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
                          TextButton(onPressed: () => Navigator.pop(ctx, true),
                            child: const Text('Delete', style: TextStyle(color: WeloColors.error))),
                        ],
                      ),
                    );
                    if (confirmed == true) {
                      try {
                        await ref.read(vendorRepositoryProvider).deleteAddOnItem(item['id'].toString());
                        _loadGroups();
                      } catch (e) {
                        if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e')));
                      }
                    }
                  },
                  child: const Icon(Icons.delete_outline, size: 16, color: WeloColors.error),
                ),
              ],
            ),
          )),
          const Divider(),
          TextButton.icon(
            icon: const Icon(Icons.add, size: 16),
            label: const Text('Add Item', style: TextStyle(fontSize: 12)),
            onPressed: () => _showAddEditItemDialog(group),
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton.icon(
                icon: const Icon(Icons.edit_outlined, size: 16),
                label: const Text('Edit Group', style: TextStyle(fontSize: 12)),
                onPressed: () => _showAddEditGroupDialog(group: group),
              ),
              const SizedBox(width: 8),
              TextButton.icon(
                icon: const Icon(Icons.delete_outline, size: 16, color: WeloColors.error),
                label: const Text('Delete Group', style: TextStyle(fontSize: 12, color: WeloColors.error)),
                onPressed: () async {
                  final confirmed = await showDialog<bool>(
                    context: context,
                    builder: (ctx) => AlertDialog(
                      title: const Text('Delete Group'),
                      content: Text('Delete "$name" and all its items?'),
                      actions: [
                        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
                        TextButton(onPressed: () => Navigator.pop(ctx, true),
                          child: const Text('Delete', style: TextStyle(color: WeloColors.error))),
                      ],
                    ),
                  );
                  if (confirmed == true) {
                    try {
                      await ref.read(vendorRepositoryProvider).deleteAddOnGroup(id);
                      _loadGroups();
                    } catch (e) {
                      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e')));
                    }
                  }
                },
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _showAddEditGroupDialog({dynamic group}) {
    final isEdit = group != null;
    final nameCtrl = TextEditingController(text: group?['name'] ?? '');
    String selectionType = group?['selection_type'] ?? 'multiple';
    bool isLoading = false;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: Text(isEdit ? 'Edit Group' : 'New Add-on Group'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nameCtrl,
                decoration: const InputDecoration(labelText: 'Group Name', hintText: 'e.g., Extra Toppings'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: selectionType,
                decoration: const InputDecoration(labelText: 'Selection Type'),
                items: const [
                  DropdownMenuItem(value: 'multiple', child: Text('Select multiple (checkboxes)')),
                  DropdownMenuItem(value: 'single', child: Text('Select one (radio)')),
                ],
                onChanged: (v) => setDialogState(() => selectionType = v ?? 'multiple'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            TextButton(
              onPressed: isLoading ? null : () async {
                if (nameCtrl.text.isEmpty) return;
                setDialogState(() => isLoading = true);
                try {
                  if (isEdit) {
                    await ref.read(vendorRepositoryProvider).updateAddOnGroup(
                      group['id'].toString(), {'name': nameCtrl.text, 'selection_type': selectionType});
                  } else {
                    await ref.read(vendorRepositoryProvider).createAddOnGroup(
                      {'name': nameCtrl.text, 'selection_type': selectionType});
                  }
                  if (ctx.mounted) Navigator.pop(ctx);
                  _loadGroups();
                } catch (e) {
                  setDialogState(() => isLoading = false);
                  if (ctx.mounted) {
                    ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('Failed: $e')));
                  }
                }
              },
              child: Text(isLoading ? 'Saving...' : (isEdit ? 'Save' : 'Create')),
            ),
          ],
        ),
      ),
    );
  }

  void _showAddEditItemDialog(dynamic group, {dynamic item}) {
    final isEdit = item != null;
    final nameCtrl = TextEditingController(text: item?['name'] ?? '');
    final priceCtrl = TextEditingController(text: (item?['price'] ?? '').toString());
    String taxType = item?['tax_type'] ?? 'NONE';
    bool isLoading = false;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: Text(isEdit ? 'Edit Item' : 'Add Item'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: nameCtrl,
                decoration: const InputDecoration(labelText: 'Item Name', hintText: 'e.g., Extra Cheese'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: priceCtrl,
                decoration: const InputDecoration(labelText: 'Price (₹)', hintText: '0'),
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: taxType,
                decoration: const InputDecoration(labelText: 'Tax Type'),
                items: const [
                  DropdownMenuItem(value: 'NONE', child: Text('No Tax')),
                  DropdownMenuItem(value: 'GST_5', child: Text('GST 5%')),
                  DropdownMenuItem(value: 'GST_18', child: Text('GST 18%')),
                ],
                onChanged: (v) => setDialogState(() => taxType = v ?? 'NONE'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            TextButton(
              onPressed: isLoading ? null : () async {
                if (nameCtrl.text.isEmpty) return;
                setDialogState(() => isLoading = true);
                final data = {
                  'name': nameCtrl.text,
                  'price': double.tryParse(priceCtrl.text) ?? 0,
                  'tax_type': taxType,
                };
                try {
                  if (isEdit) {
                    await ref.read(vendorRepositoryProvider).updateAddOnItem(
                      item['id'].toString(), data);
                  } else {
                    await ref.read(vendorRepositoryProvider).addAddOnItem(
                      group['id'].toString(), data);
                  }
                  if (ctx.mounted) Navigator.pop(ctx);
                  _loadGroups();
                } catch (e) {
                  setDialogState(() => isLoading = false);
                  if (ctx.mounted) {
                    ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('Failed: $e')));
                  }
                }
              },
              child: Text(isLoading ? 'Saving...' : (isEdit ? 'Save' : 'Add')),
            ),
          ],
        ),
      ),
    );
  }
}
