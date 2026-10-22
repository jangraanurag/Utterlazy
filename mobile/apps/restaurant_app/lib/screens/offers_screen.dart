import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';

class OffersScreen extends ConsumerStatefulWidget {
  const OffersScreen({super.key});

  @override
  ConsumerState<OffersScreen> createState() => _OffersScreenState();
}

class _OffersScreenState extends ConsumerState<OffersScreen> {
  int _selectedTab = 0;
  final _tabs = ['Active', 'Combo', 'Ended'];
  bool _isLoading = true;
  List<dynamic> _offers = [];

  @override
  void initState() {
    super.initState();
    _loadOffers();
  }

  Future<void> _loadOffers() async {
    setState(() => _isLoading = true);
    try {
      final repo = ref.read(vendorRepositoryProvider);
      final offers = await repo.getOffers();
      if (mounted) setState(() { _offers = offers; _isLoading = false; });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  List<dynamic> get _filteredOffers {
    switch (_selectedTab) {
      case 0: return _offers.where((o) => o['status'] == 'active').toList();
      case 1: return _offers.where((o) => o['status'] == 'scheduled').toList();
      case 2: return _offers.where((o) => o['status'] == 'paused' || o['status'] == 'ended').toList();
      default: return _offers;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: WeloAppBar(
        title: 'Offers',
        actions: [
          IconButton(
            icon: const Icon(Icons.add_rounded),
            onPressed: () => _showCreateOfferDialog(),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : Column(
              children: [
                Container(
                  height: 44,
                  margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    itemCount: _tabs.length,
                    itemBuilder: (context, index) {
                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: WeloChip(
                          label: _tabs[index],
                          isSelected: index == _selectedTab,
                          onTap: () => setState(() => _selectedTab = index),
                        ),
                      );
                    },
                  ),
                ),
                Expanded(
                  child: _filteredOffers.isEmpty
                      ? Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.local_offer_outlined, size: 64, color: WeloColors.textLight),
                              const SizedBox(height: 12),
                              const Text('No offers yet',
                                style: TextStyle(color: WeloColors.textBody)),
                              const SizedBox(height: 16),
                              WeloPrimaryButton(
                                text: 'Create Offer',
                                onPressed: () => _showCreateOfferDialog(),
                              ),
                            ],
                          ),
                        )
                      : RefreshIndicator(
                          onRefresh: _loadOffers,
                          color: WeloColors.sovereignOrange,
                          child: ListView.builder(
                            padding: const EdgeInsets.all(16),
                            itemCount: _filteredOffers.length,
                            itemBuilder: (context, index) => _buildOfferCard(_filteredOffers[index]),
                          ),
                        ),
                ),
              ],
            ),
    );
  }

  Widget _buildOfferCard(dynamic offer) {
    final id = offer['id']?.toString() ?? '';
    final title = offer['title']?.toString() ?? 'Offer';
    final type = offer['type']?.toString() ?? 'percentage';
    final discount = double.tryParse((offer['discount_value'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final maxDiscount = double.tryParse((offer['max_discount'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final minOrder = double.tryParse((offer['min_order_value'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final budget = double.tryParse((offer['budget'] ?? 0)?.toString() ?? '0') ?? 0.0;
    final used = int.tryParse((offer['used_count'] ?? 0)?.toString() ?? '0') ?? 0;
    final status = offer['status']?.toString() ?? 'active';
    final startDate = offer['start_date']?.toString() ?? '';
    final endDate = offer['end_date']?.toString() ?? '';

    String typeLabel;
    Color typeColor;
    switch (type) {
      case 'percentage':
        typeLabel = '${discount.toInt()}% OFF';
        typeColor = WeloColors.success;
        break;
      case 'flat':
        typeLabel = '₹${discount.toInt()} OFF';
        typeColor = WeloColors.sovereignOrange;
        break;
      case 'bogo':
        typeLabel = 'BOGO';
        typeColor = Colors.purple;
        break;
      case 'free_delivery':
        typeLabel = 'Free Delivery';
        typeColor = WeloColors.sovereignTeal;
        break;
      default:
        typeLabel = '${discount.toInt()}% OFF';
        typeColor = WeloColors.success;
    }

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: typeColor.withValues(alpha: 0.078),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(typeLabel, style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: typeColor)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 12,
            runSpacing: 4,
            children: [
              if (minOrder > 0)
                _buildOfferMeta('Min: ₹${minOrder.toInt()}'),
              if (maxDiscount > 0)
                _buildOfferMeta('Max: ₹${maxDiscount.toInt()}'),
              if (budget > 0)
                _buildOfferMeta('Budget: ₹${budget.toInt()}'),
              _buildOfferMeta('Used: $used'),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              if (startDate.isNotEmpty)
                Text('${_formatDate(startDate)} - ${_formatDate(endDate)}',
                  style: const TextStyle(color: WeloColors.textBody, fontSize: 11)),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: status == 'active' ? WeloColors.success.withValues(alpha: 0.078) :
                         status == 'paused' ? WeloColors.error.withValues(alpha: 0.078) :
                         WeloColors.textLight.withValues(alpha: 0.078),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(status[0].toUpperCase() + status.substring(1),
                  style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold,
                    color: status == 'active' ? WeloColors.success :
                           status == 'paused' ? WeloColors.error :
                           WeloColors.textLight)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              OutlinedButton.icon(
                onPressed: () => _toggleOffer(id, status),
                icon: Icon(status == 'active' ? Icons.pause : Icons.play_arrow, size: 14),
                label: Text(status == 'active' ? 'Pause' : 'Resume',
                  style: const TextStyle(fontSize: 11)),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  foregroundColor: status == 'active' ? WeloColors.error : WeloColors.success,
                ),
              ),
              const SizedBox(width: 8),
              PopupMenuButton<String>(
                onSelected: (v) {
                  if (v == 'edit') _showEditOfferDialog(offer);
                  if (v == 'delete') _confirmDelete(offer);
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

  Widget _buildOfferMeta(String text) {
    return Text(text, style: const TextStyle(color: WeloColors.textBody, fontSize: 11));
  }

  void _showCreateOfferDialog() {
    _showOfferFormDialog(null);
  }

  void _showEditOfferDialog(dynamic offer) {
    _showOfferFormDialog(offer);
  }

  void _showOfferFormDialog(dynamic existing) {
    final titleCtrl = TextEditingController(text: existing?['title']?.toString() ?? '');
    final descCtrl = TextEditingController(text: existing?['description']?.toString() ?? '');
    final discountCtrl = TextEditingController(text: existing != null ? (existing['discount_value'] ?? 0).toString() : '');
    final maxDiscountCtrl = TextEditingController(text: existing != null ? (existing['max_discount'] ?? 0).toString() : '');
    final minOrderCtrl = TextEditingController(text: existing != null ? (existing['min_order_value'] ?? 0).toString() : '');
    final budgetCtrl = TextEditingController(text: existing != null ? (existing['budget'] ?? 0).toString() : '');
    final startCtrl = TextEditingController(text: existing?['start_date']?.toString() ?? '');
    final endCtrl = TextEditingController(text: existing?['end_date']?.toString() ?? '');

    String type = existing?['type'] ?? 'percentage';
    String applicableType = existing?['applicable_type'] ?? 'all';
    bool isLoading = false;
    final isEdit = existing != null;
    final id = existing?['id']?.toString() ?? '';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(ctx).viewInsets.bottom + 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(isEdit ? 'Edit Offer' : 'Create Offer',
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 16),

              // Offer type
              DropdownButtonFormField<String>(
                initialValue: type,
                decoration: InputDecoration(labelText: 'Offer Type',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14)),
                items: ['percentage', 'flat', 'bogo', 'free_delivery'].map((t) => DropdownMenuItem(
                  value: t, child: Text(t == 'percentage' ? 'Percentage Off' :
                       t == 'flat' ? 'Flat Amount Off' :
                       t == 'bogo' ? 'Buy One Get One' : 'Free Delivery'))).toList(),
                onChanged: (v) => setSheetState(() => type = v ?? 'percentage'),
              ),
              const SizedBox(height: 12),

              WeloTextField(label: 'Offer Title', controller: titleCtrl, hintText: 'e.g., Summer Special'),
              const SizedBox(height: 12),

              if (type != 'free_delivery')
                Row(
                  children: [
                    Expanded(
                      child: WeloTextField(label: type == 'percentage' ? 'Discount %' : 'Amount (₹)',
                        controller: discountCtrl, keyboardType: TextInputType.number),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: WeloTextField(label: 'Max Discount (₹)',
                        controller: maxDiscountCtrl, keyboardType: TextInputType.number),
                    ),
                  ],
                ),
              if (type != 'free_delivery') const SizedBox(height: 12),

              Row(
                children: [
                  Expanded(
                    child: WeloTextField(label: 'Min Order (₹)',
                      controller: minOrderCtrl, keyboardType: TextInputType.number),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: WeloTextField(label: 'Budget (₹)',
                      controller: budgetCtrl, keyboardType: TextInputType.number),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              WeloTextField(label: 'Description', controller: descCtrl, maxLines: 2),
              const SizedBox(height: 12),

              Row(
                children: [
                  Expanded(
                    child: WeloTextField(label: 'Start Date (YYYY-MM-DD)', controller: startCtrl, hintText: '2026-07-01'),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: WeloTextField(label: 'End Date (YYYY-MM-DD)', controller: endCtrl, hintText: '2026-07-31'),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Applicable to
              DropdownButtonFormField<String>(
                initialValue: applicableType,
                decoration: InputDecoration(labelText: 'Applicable To',
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14)),
                items: [
                  DropdownMenuItem(value: 'all', child: const Text('All Items')),
                  DropdownMenuItem(value: 'items', child: const Text('Specific Items')),
                  DropdownMenuItem(value: 'categories', child: const Text('Specific Categories')),
                ],
                onChanged: (v) => setSheetState(() => applicableType = v ?? 'all'),
              ),
              const SizedBox(height: 20),

              SizedBox(
                width: double.infinity,
                child: WeloPrimaryButton(
                  text: isLoading ? 'Saving...' : (isEdit ? 'Update Offer' : 'Create Offer'),
                  onPressed: isLoading ? () {} : () {
                    setSheetState(() => isLoading = true);
                    final data = <String, dynamic>{
                      'type': type,
                      'title': titleCtrl.text,
                      'description': descCtrl.text,
                      'discount_value': double.tryParse(discountCtrl.text) ?? 0,
                      'max_discount': double.tryParse(maxDiscountCtrl.text) ?? 0,
                      'min_order_value': double.tryParse(minOrderCtrl.text) ?? 0,
                      'budget': double.tryParse(budgetCtrl.text) ?? 0,
                      'applicable_type': applicableType,
                      'start_date': startCtrl.text,
                      'end_date': endCtrl.text,
                    };

                    final repo = ref.read(vendorRepositoryProvider);
                    final Future<Map<String, dynamic>> future = isEdit
                        ? repo.updateOffer(id, data)
                        : repo.addOffer(data);

                    future.then((_) {
                      if (!mounted) return;
                      Navigator.pop(ctx);
                      _loadOffers();
                    }).catchError((e) {
                      setSheetState(() => isLoading = false);
                      if (ctx.mounted) {
                        ScaffoldMessenger.of(ctx).showSnackBar(
                          SnackBar(content: Text('Failed: $e')));
                      }
                    });
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _toggleOffer(String id, String currentStatus) async {
    try {
      await ref.read(vendorRepositoryProvider).toggleOfferStatus(id);
      _loadOffers();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: $e')));
      }
    }
  }

  void _confirmDelete(dynamic offer) {
    final id = offer['id']?.toString() ?? '';
    final title = offer['title']?.toString() ?? '';
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Offer'),
        content: Text('Delete "$title"?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () async {
              try {
                await ref.read(vendorRepositoryProvider).removeOffer(id);
                if (!mounted) return;
                Navigator.pop(ctx);
                _loadOffers();
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(
                    SnackBar(content: Text('Failed: $e')));
                }
              }
            },
            child: const Text('Delete', style: TextStyle(color: WeloColors.error)),
          ),
        ],
      ),
    );
  }

  String _formatDate(String iso) {
    try {
      final dt = DateTime.parse(iso).toLocal();
      return '${dt.day}/${dt.month}';
    } catch (_) {
      return iso;
    }
  }
}
