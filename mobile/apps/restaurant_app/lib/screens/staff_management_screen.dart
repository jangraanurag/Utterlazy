import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class StaffManagementScreen extends ConsumerStatefulWidget {
  const StaffManagementScreen({super.key});

  @override
  ConsumerState<StaffManagementScreen> createState() => _StaffManagementScreenState();
}

class _StaffManagementScreenState extends ConsumerState<StaffManagementScreen> {
  bool _isLoading = true;
  List<dynamic> _staff = [];

  @override
  void initState() {
    super.initState();
    _loadStaff();
  }

  Future<void> _loadStaff() async {
    setState(() => _isLoading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final staff = await repo.getStaffMembers();
      if (mounted) setState(() { _staff = staff; _isLoading = false; });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Color _roleColor(String? role) {
    switch (role) {
      case 'owner': return WeloColors.sovereignGold;
      case 'manager': return WeloColors.sovereignTeal;
      case 'kitchen': return WeloColors.sovereignOrange;
      default: return WeloColors.textBody;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Staff Management',
        actions: [
          IconButton(
            icon: const Icon(Icons.add_rounded),
            onPressed: () => _showAddStaffDialog(),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : _staff.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.people_outline_rounded, size: 64, color: WeloColors.textLight),
                      const SizedBox(height: 12),
                      const Text('No staff members added yet',
                        style: TextStyle(color: WeloColors.textBody)),
                      const SizedBox(height: 16),
                      WeloPrimaryButton(
                        text: 'Add Staff',
                        onPressed: () => _showAddStaffDialog(),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadStaff,
                  color: WeloColors.sovereignOrange,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(16),
                    itemCount: _staff.length,
                    itemBuilder: (context, index) => _buildStaffCard(_staff[index]),
                  ),
                ),
    );
  }

  Widget _buildStaffCard(dynamic member) {
    final name = member['name']?.toString() ?? 'Unknown';
    final role = member['role']?.toString() ?? 'staff';
    final email = member['email']?.toString() ?? '';
    final isActive = member['is_active'] == true || member['is_active'] == 1;

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: _roleColor(role).withValues(alpha: 0.078),
            child: Text(name.isNotEmpty ? name[0].toUpperCase() : '?',
              style: TextStyle(color: _roleColor(role), fontWeight: FontWeight.bold)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: _roleColor(role).withValues(alpha: 0.078),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(role[0].toUpperCase() + role.substring(1),
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: _roleColor(role))),
                    ),
                    const SizedBox(width: 6),
                    Expanded(child: Text(email, style: const TextStyle(color: WeloColors.textBody, fontSize: 12))),
                  ],
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: isActive ? WeloColors.success.withValues(alpha: 0.078) : WeloColors.textLight.withValues(alpha: 0.078),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              isActive ? 'Active' : 'Inactive',
              style: TextStyle(
                fontSize: 11, fontWeight: FontWeight.bold,
                color: isActive ? WeloColors.success : WeloColors.textLight,
              ),
            ),
          ),
          const SizedBox(width: 4),
          PopupMenuButton<String>(
            onSelected: (v) {
              if (v == 'edit') _showEditStaffDialog(member);
              if (v == 'delete') _confirmDelete(member);
            },
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'edit', child: Text('Edit')),
              const PopupMenuItem(value: 'delete', child: Text('Remove')),
            ],
          ),
        ],
      ),
    );
  }

  void _showAddStaffDialog() {
    final nameCtrl = TextEditingController();
    final emailCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();
    String selectedRole = 'staff';

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
            const Text('Add Staff Member', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            WeloTextField(label: 'Name', controller: nameCtrl, hintText: 'Full name'),
            const SizedBox(height: 12),
            WeloTextField(label: 'Email', controller: emailCtrl, hintText: 'email@example.com'),
            const SizedBox(height: 12),
            WeloTextField(label: 'Phone (optional)', controller: phoneCtrl, hintText: 'Phone number'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: selectedRole,
              decoration: InputDecoration(
                labelText: 'Role',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
              ),
              items: ['owner', 'manager', 'kitchen', 'staff'].map((r) =>
                DropdownMenuItem(value: r, child: Text(r[0].toUpperCase() + r.substring(1)))).toList(),
              onChanged: (v) => selectedRole = v ?? 'staff',
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: WeloPrimaryButton(
                text: 'Add Staff',
                onPressed: () async {
                  if (nameCtrl.text.isEmpty || emailCtrl.text.isEmpty) return;
                  final data = {
                    'name': nameCtrl.text,
                    'email': emailCtrl.text,
                    'role': selectedRole,
                  };
                  if (phoneCtrl.text.isNotEmpty) data['phone'] = phoneCtrl.text;
                  try {
                    await ref.read(vendorRepositoryProvider).addStaffMember(data);
                    if (!mounted) return;
                    Navigator.pop(ctx);
                    _loadStaff();
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

  void _showEditStaffDialog(dynamic member) {
    final id = member['id']?.toString() ?? '';
    final nameCtrl = TextEditingController(text: member['name']?.toString() ?? '');
    String role = member['role']?.toString() ?? 'staff';
    bool isActive = member['is_active'] == true || member['is_active'] == 1;

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
            const Text('Edit Staff', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 16),
            WeloTextField(label: 'Name', controller: nameCtrl),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: role,
              decoration: InputDecoration(
                labelText: 'Role',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
              ),
              items: ['owner', 'manager', 'kitchen', 'staff'].map((r) =>
                DropdownMenuItem(value: r, child: Text(r[0].toUpperCase() + r.substring(1)))).toList(),
              onChanged: (v) => role = v ?? 'staff',
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                const Text('Active'),
                const Spacer(),
                Switch(value: isActive, activeThumbColor: WeloColors.success, onChanged: (v) => isActive = v),
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
                    'role': role,
                    'is_active': isActive,
                  };
                  try {
                    await ref.read(vendorRepositoryProvider).updateStaffMember(id, data);
                    if (!mounted) return;
                    Navigator.pop(ctx);
                    _loadStaff();
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

  void _confirmDelete(dynamic member) {
    final id = member['id']?.toString() ?? '';
    final name = member['name']?.toString() ?? '';

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Remove Staff'),
        content: Text('Remove $name from staff?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () async {
              try {
                await ref.read(vendorRepositoryProvider).removeStaffMember(id);
                if (!mounted) return;
                Navigator.pop(ctx);
                _loadStaff();
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(
                    SnackBar(content: Text('Failed to remove: $e')));
                }
              }
            },
            child: const Text('Remove', style: TextStyle(color: WeloColors.error)),
          ),
        ],
      ),
    );
  }
}
