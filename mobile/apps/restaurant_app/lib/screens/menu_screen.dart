import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ui/ui.dart';
import 'package:core/core.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import 'addon_groups_screen.dart';

class MenuScreen extends ConsumerStatefulWidget {
  const MenuScreen({super.key});

  @override
  ConsumerState<MenuScreen> createState() => _MenuScreenState();
}

class _MenuScreenState extends ConsumerState<MenuScreen> {
  String _selectedCategoryId = 'all';
  bool _isLoading = true;
  List<dynamic> _categories = [];
  List<dynamic> _menuItems = [];

  @override
  void initState() {
    super.initState();
    _loadMenu();
  }

  Future<void> _loadMenu() async {
    setState(() => _isLoading = true);
    try {
      final vendorRepo = ref.read(vendorRepositoryProvider);
      final response = await vendorRepo.getMenu();
      if (response['categories'] != null) {
        final cats = response['categories'] as List<dynamic>;
        final uncategorized = response['uncategorized'] as List<dynamic>? ?? [];
        if (mounted) {
          setState(() {
            _categories = cats;
            _menuItems = [];
            for (final c in cats) {
              final items = c['menu_items'] as List<dynamic>? ?? [];
              _menuItems.addAll(items.map((i) {
                i['category_name'] = c['name'] ?? '';
                return i;
              }));
            }
            _menuItems.addAll(uncategorized.map((i) {
              i['category_name'] = 'Uncategorized';
              return i;
            }));
            _isLoading = false;
          });
        }
      } else {
        final List<dynamic> items = response is List ? response : (response['items'] ?? []);
        if (mounted) {
          setState(() { _menuItems = items; _isLoading = false; });
        }
      }
    } catch (e) {
      if (mounted) { setState(() => _isLoading = false); }
    }
  }

  List<dynamic> get _filteredItems {
    if (_selectedCategoryId == 'all') return _menuItems;
    return _menuItems.where((i) => (i['category_id']?.toString() ?? '') == _selectedCategoryId).toList();
  }

  List<dynamic> get _activeCategories {
    final seen = <String>{};
    final result = <dynamic>[];
    for (final item in _menuItems) {
      final catName = item['category_name']?.toString() ?? 'Uncategorized';
      final catId = item['category_id']?.toString() ?? '';
      if (seen.add(catId)) {
        result.add({'id': catId, 'name': catName});
      }
    }
    return result;
  }

  @override
  Widget build(BuildContext context) {
    final activeCats = _activeCategories;

    return Scaffold(
      appBar: WeloAppBar(
        title: 'Menu',
        actions: [
          IconButton(
            icon: const Icon(Icons.inventory_2_outlined, size: 22),
            tooltip: 'Add-on Groups',
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AddOnGroupsScreen())).then((_) => _loadMenu()),
          ),
          IconButton(
            icon: const Icon(Icons.category_rounded, size: 22),
            tooltip: 'Manage Categories',
            onPressed: () => _showCategoryManager(context),
          ),
          IconButton(
            icon: const Icon(Icons.add_rounded, size: 24),
            onPressed: () => _showAddEditItemDialog(),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : Column(
              children: [
                SizedBox(
                  height: 50,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: 1 + activeCats.length,
                    itemBuilder: (context, index) {
                      if (index == 0) {
                        return Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: WeloChip(
                            label: 'All (${_menuItems.length})',
                            isSelected: _selectedCategoryId == 'all',
                            onTap: () => setState(() => _selectedCategoryId = 'all'),
                          ),
                        );
                      }
                      final cat = activeCats[index - 1];
                      final catId = cat['id']?.toString() ?? '';
                      final catName = cat['name']?.toString() ?? '';
                      final count = _menuItems.where((i) =>
                        (i['category_id']?.toString() ?? '') == catId).length;
                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: WeloChip(
                          label: '$catName ($count)',
                          isSelected: _selectedCategoryId == catId,
                          onTap: () => setState(() => _selectedCategoryId = catId),
                        ),
                      );
                    },
                  ),
                ),
                Expanded(
                  child: _filteredItems.isEmpty
                      ? Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.menu_book_outlined, size: 64, color: WeloColors.textLight),
                              const SizedBox(height: 12),
                              const Text('No items in this category',
                                style: TextStyle(color: WeloColors.textBody)),
                            ],
                          ),
                        )
                      : RefreshIndicator(
                          onRefresh: _loadMenu,
                          color: WeloColors.sovereignOrange,
                          child: ListView.builder(
                            padding: const EdgeInsets.all(16),
                            itemCount: _filteredItems.length,
                            itemBuilder: (context, index) => _buildMenuItem(_filteredItems[index]),
                          ),
                        ),
                ),
              ],
            ),
    );
  }

  Widget _buildMenuItem(dynamic item) {
    final id = item['id']?.toString() ?? '';
    final name = item['name']?.toString() ?? 'Item';
    final price = double.tryParse(item['price']?.toString() ?? '0') ?? 0.0;
    final isAvailable = item['is_available'] == 1 || item['is_available'] == true;
    final prepType = item['prep_type']?.toString() ?? '';
    final isVeg = prepType == 'Veg' || prepType == 'Vegan' || prepType == 'Jain' || prepType.isEmpty;
    final description = item['description']?.toString() ?? '';
    final catName = item['category_name']?.toString() ?? '';
    final imageUrl = item['image_url']?.toString() ?? '';
    final hasVariants = item['variant_type'] != null;
    final hasAddOns = item['add_on_groups'] != null && (item['add_on_groups'] as List?)?.isNotEmpty == true;

    return WeloCard(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (imageUrl.isNotEmpty)
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: SizedBox(
                width: 60, height: 60,
                child: Image.network(imageUrl, fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => Container(color: WeloColors.textLight.withValues(alpha: 0.118)),
                ),
              ),
            ),
          if (imageUrl.isNotEmpty) const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(2),
                      decoration: BoxDecoration(
                        border: Border.all(color: isVeg ? WeloColors.success : WeloColors.error, width: 1.5),
                        borderRadius: BorderRadius.circular(3),
                      ),
                      child: Icon(isVeg ? Icons.circle : Icons.play_arrow,
                        size: 8, color: isVeg ? WeloColors.success : WeloColors.error),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    ),
                  ],
                ),
                if (description.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(description, style: const TextStyle(color: WeloColors.textBody, fontSize: 12),
                    maxLines: 2, overflow: TextOverflow.ellipsis),
                ],
                const SizedBox(height: 4),
                Row(
                  children: [
                    Text('₹${price.toInt()}', style: const TextStyle(
                      fontWeight: FontWeight.bold, color: WeloColors.sovereignOrange, fontSize: 15)),
                    if (hasVariants) ...[
                      const SizedBox(width: 6),
                      Icon(Icons.category, size: 14, color: WeloColors.textLight),
                    ],
                    if (hasAddOns) ...[
                      const SizedBox(width: 4),
                      Icon(Icons.add_circle_outline, size: 14, color: WeloColors.textLight),
                    ],
                    if (catName.isNotEmpty) ...[
                      const SizedBox(width: 8),
                      Expanded(child: Text(catName, style: const TextStyle(color: WeloColors.textLight, fontSize: 11), overflow: TextOverflow.ellipsis)),
                    ],
                  ],
                ),
              ],
            ),
          ),
          Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Switch(
                value: isAvailable,
                activeThumbColor: WeloColors.success,
                onChanged: (val) => _toggleAvailability(id, item, val),
              ),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  InkWell(
                    onTap: () => _showAddEditItemDialog(item: item),
                    child: const Icon(Icons.edit_outlined, size: 18, color: WeloColors.textBody),
                  ),
                  const SizedBox(width: 4),
                  InkWell(
                    onTap: () => _showDeleteConfirm(item),
                    child: const Icon(Icons.delete_outline, size: 18, color: WeloColors.error),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _toggleAvailability(String id, dynamic item, bool available) async {
    setState(() => item['is_available'] = available);
    try {
      await ref.read(vendorRepositoryProvider).editMenuItem(id, {'is_available': available});
    } catch (e) {
      setState(() => item['is_available'] = !available);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed to update: $e')));
      }
    }
  }

  Future<void> _showAddEditItemDialog({dynamic item}) async {
    final isEdit = item != null;
    final nameCtrl = TextEditingController(text: item?['name'] ?? '');
    final priceCtrl = TextEditingController(text: (item?['price'] ?? '').toString());
    final descCtrl = TextEditingController(text: item?['description'] ?? '');
    final servesCtrl = TextEditingController(text: item?['serves_count']?.toString() ?? '');

    String? imageUrl = item?['image_url']?.toString();
    String? selectedCategoryId = item?['category_id']?.toString();
    String? selectedGlobalCategoryId = item?['global_category_id']?.toString();
    String prepType = item?['prep_type'] ?? 'Veg';
    bool fastingSuitable = item?['fasting_suitable'] == true || item?['fasting_suitable'] == 1;
    String taxType = item?['tax_type'] ?? 'NONE';
    bool taxIncluded = item?['tax_included'] == true || item?['tax_included'] == 1;
    String nameLocal = item?['name_local'] ?? '';
    String reelUrl = item?['reel_url'] ?? '';
    List<dynamic> globalCats = [];

    // Load global categories
    try {
      final response = await ref.read(vendorRepositoryProvider).getMenu();
      if (response['global_categories'] != null) {
        globalCats = response['global_categories'] as List<dynamic>;
      }
    } catch (_) {}

    // Variant state
    String variantAxis = item?['variant_type']?['axis'] ?? '';
    String variantUnit = item?['variant_type']?['unit'] ?? '';
    List<Map<String, dynamic>> variants = [];
    if (item?['variant_type']?['variants'] != null) {
      for (final v in item['variant_type']['variants']) {
        variants.add({
          'label': v['label'] ?? '',
          'unit': v['unit'] ?? variantUnit,
          'price_impact': (v['price_impact'] ?? 0).toString(),
          'is_default': v['is_default'] == true || v['is_default'] == 1,
        });
      }
    }

    // Add-on groups
    List<String> selectedAddOnGroupIds = [];
    if (item?['add_on_groups'] != null) {
      for (final g in item['add_on_groups']) {
        selectedAddOnGroupIds.add(g['id'].toString());
      }
    }
    List<dynamic> allAddOnGroups = [];

    // Images
    List<Map<String, dynamic>> images = [];
    if (item?['images'] != null) {
      for (final img in item['images']) {
        images.add({
          'image_url': img['image_url'] ?? '',
          'is_cover': img['is_cover'] == true || img['is_cover'] == 1,
          'sort_order': img['sort_order'] ?? 0,
        });
      }
    }

    // Packaging
    String packingLevel = item?['packaging']?['level'] ?? '';
    String packingType = item?['packaging']?['charge_type'] ?? 'fixed';
    String packingValue = (item?['packaging']?['charge_value'] ?? '').toString();
    String packingTaxType = item?['packaging']?['tax_type'] ?? 'NONE';

    bool isLoading = false;

    // Load add-on groups for selection
    ref.read(vendorRepositoryProvider).getAddOnGroups().then((groups) {
      allAddOnGroups = groups;
    }).catchError((_) {});

    // Page state for the tabbed form — kept OUTSIDE the StatefulBuilder
    // builder so rebuilds don't recreate the controller (which reset the
    // form back to page 0 on every setState).
    int currentPage = 0;
    final pageCtrl = PageController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) {
          return SizedBox(
            height: MediaQuery.of(ctx).size.height * 0.85,
            child: Padding(
              padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(ctx).viewInsets.bottom + 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(isEdit ? 'Edit: ${item['name']}' : 'Add Menu Item',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(ctx),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  // Step indicator
                  Row(
                    children: [
                      _stepChip(0, 'Basics', currentPage),
                      const SizedBox(width: 4),
                      _stepChip(1, 'Classification', currentPage),
                      const SizedBox(width: 4),
                      _stepChip(2, 'Pricing', currentPage),
                      const SizedBox(width: 4),
                      _stepChip(3, 'Variants', currentPage),
                      const SizedBox(width: 4),
                      _stepChip(4, 'More', currentPage),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Expanded(
                    child: PageView(
                      controller: pageCtrl,
                      onPageChanged: (p) => setSheetState(() => currentPage = p),
                      children: [
                        // Page 0: Basics
                        _buildBasicPage(setSheetState, nameCtrl, nameLocal, descCtrl, selectedCategoryId, imageUrl, reelUrl, isLoading),
                        // Page 1: Classification
                        _buildClassificationPage(setSheetState, prepType, fastingSuitable, servesCtrl, selectedGlobalCategoryId, globalCats),
                        // Page 2: Pricing & Tax
                        _buildPricingPage(setSheetState, priceCtrl, taxType, taxIncluded,
                            packingLevel, packingType, packingValue, packingTaxType),
                        // Page 3: Variants
                        _buildVariantsPage(setSheetState, variantAxis, variantUnit, variants),
                        // Page 4: Add-ons, Images & Save
                        _buildMorePage(setSheetState, allAddOnGroups, selectedAddOnGroupIds, images, isLoading,
                            isEdit, item, nameCtrl, priceCtrl, descCtrl, servesCtrl, prepType, fastingSuitable,
                            nameLocal, reelUrl, selectedGlobalCategoryId,
                            taxType, taxIncluded, packingLevel, packingType, packingValue, packingTaxType,
                            variantAxis, variantUnit, variants, selectedCategoryId, imageUrl, ctx, pageCtrl),
                      ],
                    ),
                  ),
                  // Bottom navigation buttons
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      if (currentPage > 0)
                        TextButton(
                          onPressed: () => pageCtrl.previousPage(duration: const Duration(milliseconds: 300), curve: Curves.easeInOut),
                          child: const Text('Previous'),
                        )
                      else
                        const SizedBox.shrink(),
                      if (currentPage < 4)
                        ElevatedButton(
                          onPressed: () => pageCtrl.nextPage(duration: const Duration(milliseconds: 300), curve: Curves.easeInOut),
                          child: const Text('Next'),
                        )
                      else
                        const SizedBox.shrink(),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _stepChip(int index, String label, int current) {
    final isActive = index == current;
    final isDone = index < current;
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 6),
        decoration: BoxDecoration(
          color: isActive ? WeloColors.sovereignOrange : (isDone ? Colors.green.shade100 : Colors.grey.shade100),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(label,
          style: TextStyle(
            fontSize: 9, fontWeight: FontWeight.bold,
            color: isActive ? Colors.white : (isDone ? Colors.green : Colors.grey),
          ),
          textAlign: TextAlign.center,
        ),
      ),
    );
  }

  Widget _buildBasicPage(StateSetter setSheetState, TextEditingController nameCtrl,
      String nameLocal, TextEditingController descCtrl, String? selectedCategoryId,
      String? imageUrl, String reelUrl, bool isLoading) {
    final nameLocalCtrl = TextEditingController(text: nameLocal);
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          WeloTextField(label: 'Item Name *', controller: nameCtrl, hintText: 'e.g., Butter Chicken'),
          const SizedBox(height: 12),
          WeloTextField(label: 'Local Name', controller: nameLocalCtrl, hintText: 'e.g., बटर चिकन'),
          const SizedBox(height: 12),
          WeloTextField(label: 'Description *', controller: descCtrl, hintText: 'Describe the item...', maxLines: 3),
          const SizedBox(height: 12),
          _buildCategoryDropdown(selectedCategoryId, (v) => setSheetState(() => selectedCategoryId = v)),
          const SizedBox(height: 12),
          Row(
            children: [
              if (imageUrl != null)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: SizedBox(width: 60, height: 60,
                      child: Image.network(imageUrl, fit: BoxFit.cover,
                        errorBuilder: (_, _, _) => Container(color: Colors.grey.shade200)),
                    ),
                  ),
                ),
              TextButton.icon(
                onPressed: () async {
                  final picker = ImagePicker();
                  final picked = await picker.pickImage(source: ImageSource.gallery);
                  if (picked != null) {
                    try {
                      final repo = ref.read(vendorRepositoryProvider);
                      final url = await repo.uploadFile(File(picked.path), 'menu_item');
                      setSheetState(() => imageUrl = url);
                    } catch (e) {
                      if (mounted) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Upload failed: $e'))); }
                    }
                  }
                },
                icon: const Icon(Icons.image_outlined, size: 18),
                label: const Text('Cover Photo', style: TextStyle(fontSize: 12)),
              ),
              const SizedBox(width: 8),
              IconButton(
                icon: const Icon(Icons.videocam_outlined, size: 20),
                tooltip: 'Reel URL',
                onPressed: () async {
                  final url = await showDialog<String>(
                    context: context,
                    builder: (dCtx) => AlertDialog(
                      title: const Text('Video Reel URL'),
                      content: TextField(
                        controller: TextEditingController(text: reelUrl),
                        decoration: const InputDecoration(hintText: 'https://...'),
                        onChanged: (v) => reelUrl = v,
                      ),
                      actions: [
                        TextButton(onPressed: () => Navigator.pop(dCtx), child: const Text('Cancel')),
                        TextButton(onPressed: () => Navigator.pop(dCtx, reelUrl), child: const Text('Set')),
                      ],
                    ),
                  );
                  if (url != null) setSheetState(() => reelUrl = url);
                },
              ),
            ],
          ),
        ],
      ),
    );
  }


  Widget _buildClassificationPage(StateSetter setSheetState, String prepType,
      bool fastingSuitable, TextEditingController servesCtrl,
      String? selectedGlobalCategoryId, List<dynamic> globalCats) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Food Preparation Type *', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: ['Veg', 'Non-Veg', 'Vegan', 'Jain', 'Vaishno', 'Egg'].map((type) {
              final isSel = prepType == type;
              return ChoiceChip(
                label: Text(type, style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold,
                    color: isSel ? Colors.white : Colors.black54)),
                selected: isSel,
                selectedColor: type == 'Veg' || type == 'Vegan' || type == 'Jain'
                    ? Colors.green : (type == 'Egg' ? Colors.orange : Colors.red),
                onSelected: (v) => setSheetState(() => prepType = type),
              );
            }).toList(),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              const Text('Fasting Suitable (Vrat)', style: TextStyle(fontSize: 13)),
              const SizedBox(width: 8),
              Switch(value: fastingSuitable, activeThumbColor: Colors.orange,
                onChanged: (v) => setSheetState(() => fastingSuitable = v)),
            ],
          ),
          const SizedBox(height: 12),
          WeloTextField(label: 'Serves (no. of people)', controller: servesCtrl,
            hintText: 'e.g., 2', keyboardType: TextInputType.number),
          const SizedBox(height: 16),
          // Global Category
          if (globalCats.isNotEmpty) ...[
            const Text('Global Category', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
            const SizedBox(height: 8),
            DropdownButtonFormField<String>(
              initialValue: selectedGlobalCategoryId != null && globalCats.any((g) => g['id'].toString() == selectedGlobalCategoryId) ? selectedGlobalCategoryId : null,
              decoration: const InputDecoration(
                labelText: 'Global Category',
                border: OutlineInputBorder(),
                contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              ),
              items: [
                const DropdownMenuItem(value: null, child: Text('None', style: TextStyle(fontSize: 13))),
                ...globalCats.map((g) => DropdownMenuItem(
                  value: g['id'].toString(),
                  child: Text(g['name']?.toString() ?? '', style: const TextStyle(fontSize: 13)),
                )),
              ],
              onChanged: (v) => setSheetState(() => selectedGlobalCategoryId = v),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildPricingPage(StateSetter setSheetState, TextEditingController priceCtrl,
      String taxType, bool taxIncluded,
      String packingLevel, String packingType, String packingValue, String packingTaxType) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          WeloTextField(label: 'Base Price (₹) *', controller: priceCtrl,
            hintText: '0', keyboardType: TextInputType.number),
          const SizedBox(height: 16),
          const Text('Tax Type *', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              _taxChip('NONE', 'No GST', taxType, (v) => setSheetState(() => taxType = v)),
              _taxChip('GST_5', 'GST 5%', taxType, (v) => setSheetState(() => taxType = v)),
              _taxChip('GST_18', 'GST 18%', taxType, (v) => setSheetState(() => taxType = v)),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              const Text('Tax Included in Price', style: TextStyle(fontSize: 13)),
              const SizedBox(width: 8),
              Switch(value: taxIncluded, activeThumbColor: WeloColors.sovereignOrange,
                onChanged: (v) => setSheetState(() => taxIncluded = v)),
            ],
          ),
          const Divider(height: 32),
          const Text('Packaging Charge (Item Level)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextField(
                  decoration: const InputDecoration(
                    labelText: 'Charge (₹)', hintText: '0',
                    border: OutlineInputBorder(),
                    contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                  keyboardType: TextInputType.number,
                  controller: TextEditingController(text: packingValue),
                  onChanged: (v) => setSheetState(() => packingValue = v),
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: packingTaxType,
                  decoration: const InputDecoration(
                    labelText: 'Tax',
                    border: OutlineInputBorder(),
                    contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                  items: const [
                    DropdownMenuItem(value: 'NONE', child: Text('No Tax', style: TextStyle(fontSize: 12))),
                    DropdownMenuItem(value: 'GST_5', child: Text('GST 5%', style: TextStyle(fontSize: 12))),
                  ],
                  onChanged: (v) => setSheetState(() => packingTaxType = v ?? 'NONE'),
                ),
              ),
            ],
          ),
          SizedBox(
            width: double.infinity,
            child: CheckboxListTile(
              title: const Text('Enable item-level packaging', style: TextStyle(fontSize: 13)),
              value: packingLevel == 'item_level',
              onChanged: (v) => setSheetState(() => packingLevel = v == true ? 'item_level' : ''),
              controlAffinity: ListTileControlAffinity.leading,
              contentPadding: EdgeInsets.zero,
            ),
          ),
        ],
      ),
    );
  }

  Widget _taxChip(String value, String label, String current, ValueChanged<String> onChanged) {
    final isSel = current == value;
    return ChoiceChip(
      label: Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold,
          color: isSel ? Colors.white : Colors.black54)),
      selected: isSel,
      onSelected: (v) => onChanged(value),
    );
  }

  Widget _buildVariantsPage(StateSetter setSheetState, String variantAxis,
      String variantUnit, List<Map<String, dynamic>> variants) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Variant Type (Optional)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          const SizedBox(height: 8),
          DropdownButtonFormField<String>(
            initialValue: variantAxis.isEmpty ? null : variantAxis,
            decoration: const InputDecoration(
              labelText: 'Variant Axis',
              border: OutlineInputBorder(),
              contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            ),
            items: const [
              DropdownMenuItem(value: null, child: Text('None (single price)', style: TextStyle(fontSize: 12))),
              DropdownMenuItem(value: 'size', child: Text('Size (inch/cm)', style: TextStyle(fontSize: 12))),
              DropdownMenuItem(value: 'volume', child: Text('Volume (ml/ltr)', style: TextStyle(fontSize: 12))),
              DropdownMenuItem(value: 'quantity', child: Text('Quantity (half/full plate)', style: TextStyle(fontSize: 12))),
            ],
            onChanged: (v) {
              setSheetState(() {
                variantAxis = v ?? '';
                if (variantAxis == 'quantity') { variantUnit = ''; }
                if (variants.isEmpty && v != null) {
                  variants.add({'label': '', 'unit': variantUnit, 'price_impact': '0', 'is_default': true});
                }
              });
            },
          ),
          if (variantAxis.isNotEmpty && variantAxis != 'quantity') ...[
            const SizedBox(height: 8),
            TextField(
              decoration: const InputDecoration(
                labelText: 'Unit', hintText: 'e.g., inch, cm, ml',
                border: OutlineInputBorder(),
                contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              ),
              controller: TextEditingController(text: variantUnit),
              onChanged: (v) => setSheetState(() => variantUnit = v),
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
            ),
          ],
          if (variantAxis.isNotEmpty) ...[
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Variant Options', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                TextButton.icon(
                  icon: const Icon(Icons.add, size: 16),
                  label: const Text('Add', style: TextStyle(fontSize: 12)),
                  onPressed: () => setSheetState(() {
                    variants.add({'label': '', 'unit': variantUnit, 'price_impact': '0', 'is_default': variants.isEmpty});
                  }),
                ),
              ],
            ),
            ...variants.asMap().entries.map((entry) {
              final i = entry.key;
              final v = entry.value;
              return Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: TextField(
                              decoration: const InputDecoration(
                                labelText: 'Label', hintText: 'e.g., Full Plate',
                                border: OutlineInputBorder(),
                                contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                              ),
                              controller: TextEditingController(text: v['label'] as String? ?? ''),
                              onChanged: (val) => setSheetState(() => variants[i]['label'] = val),
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ),
                          const SizedBox(width: 8),
                          SizedBox(
                            width: 80,
                            child: TextField(
                              decoration: const InputDecoration(
                                labelText: 'Price Δ', hintText: '+50',
                                border: OutlineInputBorder(),
                                contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                              ),
                              keyboardType: TextInputType.number,
                              controller: TextEditingController(text: v['price_impact'] as String? ?? '0'),
                              onChanged: (val) => setSheetState(() => variants[i]['price_impact'] = val),
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ),
                          const SizedBox(width: 4),
                          InkWell(
                            onTap: () => setSheetState(() {
                              for (var j = 0; j < variants.length; j++) { variants[j]['is_default'] = j == i; }
                            }),
                            child: Icon(
                              v['is_default'] == true ? Icons.radio_button_checked : Icons.radio_button_off,
                              size: 20, color: v['is_default'] == true ? WeloColors.sovereignOrange : Colors.grey,
                            ),
                          ),
                          const SizedBox(width: 4),
                          InkWell(
                            onTap: () => setSheetState(() => variants.removeAt(i)),
                            child: const Icon(Icons.delete_outline, size: 18, color: WeloColors.error),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            }),
          ],
        ],
      ),
    );
  }

  Widget _buildMorePage(StateSetter setSheetState, List<dynamic> allAddOnGroups,
      List<String> selectedAddOnGroupIds, List<Map<String, dynamic>> images,
      bool isLoading, bool isEdit, dynamic item,
      TextEditingController nameCtrl, TextEditingController priceCtrl, TextEditingController descCtrl,
      TextEditingController servesCtrl, String prepType, bool fastingSuitable,
      String nameLocal, String reelUrl, String? selectedGlobalCategoryId,
      String taxType, bool taxIncluded,
      String packingLevel, String packingType, String packingValue, String packingTaxType,
      String variantAxis, String variantUnit, List<Map<String, dynamic>> variants,
      String? selectedCategoryId, String? imageUrl, BuildContext ctx, PageController pageCtrl) {
    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Add-on Groups', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          const SizedBox(height: 8),
          if (allAddOnGroups.isEmpty)
            const Text('No add-on groups yet. Create them from the Add-on Groups screen.',
              style: TextStyle(color: Colors.grey, fontSize: 12))
          else
            ...allAddOnGroups.map((g) {
              final gid = g['id'].toString();
              final gname = g['name']?.toString() ?? '';
              final isSelected = selectedAddOnGroupIds.contains(gid);
              return CheckboxListTile(
                dense: true,
                title: Text(gname, style: const TextStyle(fontSize: 13)),
                subtitle: g['items'] != null
                    ? Text('${(g['items'] as List).length} items', style: const TextStyle(fontSize: 11))
                    : null,
                value: isSelected,
                onChanged: (v) => setSheetState(() {
                  if (v == true) { selectedAddOnGroupIds.add(gid); }
                  else { selectedAddOnGroupIds.remove(gid); }
                }),
                controlAffinity: ListTileControlAffinity.leading,
              );
            }),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.black,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              ),
              onPressed: isLoading ? null : () async {
                setSheetState(() => isLoading = true);

                final nameLocalText = nameLocal.isNotEmpty ? nameLocal : null;
                final reelText = reelUrl.isNotEmpty ? reelUrl : null;

                final data = <String, dynamic>{
                  'name': nameCtrl.text,
                  'price': double.tryParse(priceCtrl.text) ?? 0,
                  'description': descCtrl.text,
                  'prep_type': prepType,
                  'fasting_suitable': fastingSuitable,
                  'tax_type': taxType,
                  'tax_included': taxIncluded,
                  'serves_count': int.tryParse(servesCtrl.text),
                  'name_local': nameLocalText,
                  'reel_url': reelText,
                };
                if (selectedCategoryId != null) { data['category_id'] = selectedCategoryId; }
                if (selectedGlobalCategoryId != null) { data['global_category_id'] = selectedGlobalCategoryId; }
                if (imageUrl != null) { data['image_url'] = imageUrl; }

                // Variant data
                if (variantAxis.isNotEmpty) {
                  data['variant_type'] = {'axis': variantAxis, 'unit': variantAxis == 'quantity' ? null : variantUnit};
                  data['variants'] = variants.map((v) => {
                    'label': v['label'],
                    'unit': v['unit'],
                    'price_impact': double.tryParse(v['price_impact']?.toString() ?? '0') ?? 0,
                    'is_default': v['is_default'] == true,
                  }).toList();
                }

                // Add-on groups
                if (selectedAddOnGroupIds.isNotEmpty) {
                  data['add_on_group_ids'] = selectedAddOnGroupIds;
                }

                // Packaging
                if (packingLevel == 'item_level') {
                  data['packaging_charge_level'] = 'item_level';
                  data['packaging_charge_type'] = 'fixed';
                  data['packaging_charge_value'] = double.tryParse(packingValue) ?? 0;
                  data['packaging_tax_type'] = packingTaxType;
                }

                try {
                  final repo = ref.read(vendorRepositoryProvider);
                  if (isEdit) {
                    await repo.editMenuItem(item['id'].toString(), data);
                  } else {
                    await repo.addMenuItem(data);
                  }
                  if (ctx.mounted) { Navigator.pop(ctx); }
                  _loadMenu();
                } catch (e) {
                  setSheetState(() => isLoading = false);
                  if (ctx.mounted) {
                    ScaffoldMessenger.of(ctx).showSnackBar(
                      SnackBar(content: Text('${isEdit ? "Update" : "Add"} failed: $e')));
                  }
                }
              },
              child: Text(isLoading ? 'Saving...' : (isEdit ? 'Save Changes' : 'Add Item'),
                style: const TextStyle(fontWeight: FontWeight.bold, letterSpacing: 1)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryDropdown(String? selectedId, ValueChanged<String?> onChanged) {
    final allCatIds = <String, String>{};
    for (final item in _menuItems) {
      final catId = item['category_id']?.toString() ?? '';
      if (catId.isNotEmpty) { allCatIds[catId] = item['category_name']?.toString() ?? 'Category'; }
    }
    for (final c in _categories) {
      final id = c['id']?.toString() ?? '';
      if (id.isNotEmpty) { allCatIds[id] = c['name']?.toString() ?? 'Category'; }
    }

    return DropdownButtonFormField<String>(
      initialValue: selectedId != null && allCatIds.containsKey(selectedId) ? selectedId : null,
      decoration: InputDecoration(
        labelText: 'Category *',
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      ),
      items: [
        const DropdownMenuItem(value: null, child: Text('Select Category', style: TextStyle(fontSize: 13))),
        ...allCatIds.entries.map((e) => DropdownMenuItem(
          value: e.key, child: Text(e.value, style: const TextStyle(fontSize: 13)))),
      ],
      onChanged: onChanged,
    );
  }

  void _showDeleteConfirm(dynamic item) {
    final id = item['id']?.toString() ?? '';
    final name = item['name']?.toString() ?? '';
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Item'),
        content: Text('Delete "$name"? This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () async {
              try {
                await ref.read(vendorRepositoryProvider).removeMenuItem(id);
                if (!mounted) return;
                Navigator.pop(ctx);
                _loadMenu();
              } catch (e) {
                if (ctx.mounted) {
                  ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('Failed to delete: $e')));
                }
              }
            },
            child: const Text('Delete', style: TextStyle(color: WeloColors.error)),
          ),
        ],
      ),
    );
  }

  // ─── Category Management ─────────────────────────────────────────────

  void _showCategoryManager(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) => Container(
        constraints: BoxConstraints(maxHeight: MediaQuery.of(ctx).size.height * 0.7),
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Categories', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                IconButton(icon: const Icon(Icons.add_rounded, size: 22),
                  onPressed: () => _showAddCategoryDialog(ctx)),
              ],
            ),
            const SizedBox(height: 12),
            if (_categories.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Center(child: Text('No categories yet', style: TextStyle(color: WeloColors.textBody))),
              )
            else
              Flexible(
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: _categories.length,
                  itemBuilder: (context, index) {
                    final cat = _categories[index];
                    final catId = cat['id']?.toString() ?? '';
                    final catName = cat['name']?.toString() ?? '';
                    final itemCount = _menuItems.where((i) => (i['category_id']?.toString() ?? '') == catId).length;
                    return ListTile(
                      dense: true,
                      title: Text(catName),
                      subtitle: Text('$itemCount items', style: const TextStyle(fontSize: 12)),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(icon: const Icon(Icons.edit_outlined, size: 18),
                            onPressed: () => _showEditCategoryDialog(ctx, cat)),
                          IconButton(icon: const Icon(Icons.delete_outline, size: 18, color: WeloColors.error),
                            onPressed: () => _confirmDeleteCategory(ctx, cat)),
                        ],
                      ),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }

  void _showAddCategoryDialog(BuildContext parentCtx) {
    final nameCtrl = TextEditingController();
    final descCtrl = TextEditingController();
    showDialog(
      context: parentCtx,
      builder: (ctx) => AlertDialog(
        title: const Text('Add Category'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Category Name', hintText: 'e.g., Starters')),
          const SizedBox(height: 12),
          TextField(controller: descCtrl, decoration: const InputDecoration(labelText: 'Description (optional)')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () async {
              if (nameCtrl.text.isEmpty) return;
              try {
                await ref.read(vendorRepositoryProvider).addCategory({'name': nameCtrl.text, 'description': descCtrl.text});
                if (!mounted) return;
                Navigator.pop(ctx);
                _loadMenu();
              } catch (e) {
                if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('Failed: $e')));
              }
            },
            child: const Text('Add'),
          ),
        ],
      ),
    );
  }

  void _showEditCategoryDialog(BuildContext parentCtx, dynamic cat) {
    final id = cat['id']?.toString() ?? '';
    final nameCtrl = TextEditingController(text: cat['name']?.toString() ?? '');
    final descCtrl = TextEditingController(text: cat['description']?.toString() ?? '');
    showDialog(
      context: parentCtx,
      builder: (ctx) => AlertDialog(
        title: const Text('Edit Category'),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Category Name')),
          const SizedBox(height: 12),
          TextField(controller: descCtrl, decoration: const InputDecoration(labelText: 'Description (optional)')),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () async {
              try {
                await ref.read(vendorRepositoryProvider).editCategory(id, {'name': nameCtrl.text, 'description': descCtrl.text});
                if (!mounted) return;
                Navigator.pop(ctx);
                _loadMenu();
              } catch (e) {
                if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('Failed: $e')));
              }
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }

  void _confirmDeleteCategory(BuildContext parentCtx, dynamic cat) {
    final id = cat['id']?.toString() ?? '';
    final name = cat['name']?.toString() ?? '';
    showDialog(
      context: parentCtx,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Category'),
        content: Text('Delete "$name"? Items will be moved to Uncategorized.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(
            onPressed: () async {
              try {
                await ref.read(vendorRepositoryProvider).removeCategory(id);
                if (!mounted) return;
                Navigator.pop(ctx);
                _loadMenu();
              } catch (e) {
                if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('Failed: $e')));
              }
            },
            child: const Text('Delete', style: TextStyle(color: WeloColors.error)),
          ),
        ],
      ),
    );
  }
}
