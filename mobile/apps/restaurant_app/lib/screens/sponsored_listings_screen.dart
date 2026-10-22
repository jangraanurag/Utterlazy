import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:core/core.dart';
import 'package:ui/ui.dart';
import 'package:intl/intl.dart';

class SponsoredListingsScreen extends ConsumerStatefulWidget {
  const SponsoredListingsScreen({super.key});

  @override
  ConsumerState<SponsoredListingsScreen> createState() => _SponsoredListingsScreenState();
}

class _SponsoredListingsScreenState extends ConsumerState<SponsoredListingsScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  List<dynamic> _ads = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadAds();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadAds() async {
    setState(() => _isLoading = true);
    try {
      final ads = await ref.read(vendorRepositoryProvider).getAds();
      if (mounted) setState(() { _ads = ads; _isLoading = false; });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  List<dynamic> get _filteredAds {
    if (_tabController.index == 0) return _ads;
    return _ads.where((a) {
      final s = (a['status'] ?? '').toString();
      return s == 'active' || s == 'scheduled';
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: WeloColors.sovereignSurface,
        elevation: 0,
        centerTitle: true,
        title: Text('SPONSORED ADS', style: GoogleFonts.spaceGrotesk(fontSize: 14, fontWeight: FontWeight.w900, letterSpacing: 1.5, color: WeloColors.sovereignText)),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: WeloColors.sovereignOrange,
          labelColor: WeloColors.sovereignOrange,
          unselectedLabelColor: Colors.grey,
          tabs: const [
            Tab(text: 'Track Ads'),
            Tab(text: 'Create Ads'),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: WeloColors.sovereignOrange))
          : TabBarView(
              controller: _tabController,
              children: [
                _buildTrackTab(),
                _buildCreateTab(),
              ],
            ),
    );
  }

  // ═══ TRACK ADS TAB ═══

  Widget _buildTrackTab() {
    return _ads.isEmpty
        ? ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: const [
              SizedBox(height: 100),
              WeloEmptyState(icon: Icons.campaign_outlined, title: 'No ad campaigns', subtitle: 'Create your first ad in the Create tab'),
            ],
          )
        : RefreshIndicator(
            onRefresh: _loadAds,
            color: WeloColors.sovereignOrange,
            child: ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              itemCount: _filteredAds.length,
              itemBuilder: (context, index) => _buildAdCard(_filteredAds[index]),
            ),
          );
  }

  Widget _buildAdCard(dynamic ad) {
    final status = ad['status']?.toString() ?? '';
    final adType = ad['ad_type']?.toString() ?? 'ppc';
    final placement = ad['placement']?.toString() ?? '';
    final weeklyBudget = (ad['weekly_budget'] is num) ? ad['weekly_budget'].toDouble() : 0.0;
    final currentSpend = (ad['current_spend'] is num) ? ad['current_spend'].toDouble() : 0.0;
    final cpc = (ad['cpc'] is num) ? ad['cpc'].toDouble() : 0.0;
    final startDate = ad['start_date']?.toString() ?? '';
    final endDate = ad['end_date']?.toString() ?? '';

    String heading = adType == 'card' ? 'Card Ad' : 'PPC';
    if (placement.isNotEmpty) heading += ' — ${placement[0].toUpperCase()}${placement.substring(1)}';

    Color statusColor;
    switch (status) {
      case 'active': statusColor = Colors.green; break;
      case 'paused': statusColor = Colors.orange; break;
      case 'completed': statusColor = Colors.grey; break;
      default: statusColor = Colors.blue; break; // scheduled
    }

    final spendRatio = weeklyBudget > 0 ? (currentSpend / weeklyBudget).clamp(0, 1) : 0.0;

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
                  decoration: BoxDecoration(color: WeloColors.sovereignOrange, borderRadius: BorderRadius.circular(8)),
                  child: Text(heading, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(8)),
                  child: Text(status.toUpperCase(), style: TextStyle(color: statusColor, fontSize: 9, fontWeight: FontWeight.bold)),
                ),
                const Spacer(),
                PopupMenuButton<String>(
                  onSelected: (v) {
                    if (v == 'toggle') _toggleAd(ad);
                    else if (v == 'delete') _deleteAd(ad);
                    else if (v == 'details') _showDetails(ad);
                    else if (v == 'performance') _showPerformance(ad);
                  },
                  itemBuilder: (_) => <PopupMenuEntry<String>>[
                    const PopupMenuItem(value: 'details', child: Text('View Details')),
                    const PopupMenuItem(value: 'performance', child: Text('Performance')),
                    PopupMenuItem(value: 'toggle', child: Text(status == 'active' ? 'Pause' : 'Activate')),
                    const PopupMenuItem(value: 'delete', child: Text('Delete', style: TextStyle(color: Colors.red))),
                  ],
                  icon: const Icon(Icons.more_vert, size: 20),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                _stat('Budget', '₹${weeklyBudget.toInt()}/wk'),
                const SizedBox(width: 16),
                _stat('Spent', '₹${currentSpend.toInt()}'),
                const SizedBox(width: 16),
                _stat('CPC', '₹${cpc.toStringAsFixed(1)}'),
              ],
            ),
            const SizedBox(height: 8),
            Text('$startDate — $endDate', style: const TextStyle(color: Colors.black54, fontSize: 11)),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: spendRatio,
                backgroundColor: Colors.grey.shade200,
                color: spendRatio > 0.8 ? Colors.red : Colors.green,
                minHeight: 4,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _stat(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
        Text(label, style: const TextStyle(color: Colors.black54, fontSize: 10)),
      ],
    );
  }

  // ═══ CREATE ADS TAB ═══

  Widget _buildCreateTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          _buildAdTypeCard('PPC', 'Pay-Per-Click — charged per click on listings/search', Icons.search, () => _showAdForm('ppc')),
          const SizedBox(height: 12),
          _buildAdTypeCard('Card Ad', 'Featured restaurant card/carousel placement', Icons.campaign, () => _showAdForm('card')),
        ],
      ),
    );
  }

  Widget _buildAdTypeCard(String title, String subtitle, IconData icon, VoidCallback onTap) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        leading: CircleAvatar(backgroundColor: WeloColors.sovereignOrange.withValues(alpha: 0.1), child: Icon(icon, color: WeloColors.sovereignOrange)),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Text(subtitle, style: const TextStyle(fontSize: 12)),
        trailing: const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }

  // ═══ AD FORM ═══

  void _showAdForm(String adType) {
    final weeklyBudgetCtrl = TextEditingController();
    final cpcCtrl = TextEditingController();
    final durationCtrl = TextEditingController(text: '1');
    final startCtrl = TextEditingController();
    String placement = 'listings';
    String targetCustomers = 'all';
    bool allDay = true;
    String timeFrom = '09:00';
    String timeTo = '22:00';
    bool isLoading = false;
    Map<String, dynamic>? recommendations;
    bool loadingRecs = true;

    // Fetch recommendations
    ref.read(vendorRepositoryProvider).apiClient.dio.get('/vendor/ads/recommendations', queryParameters: {
      'ad_type': adType, 'placement': adType == 'ppc' ? placement : null, 'target_customers': targetCustomers,
    }).then((res) {
      recommendations = res.data;
      loadingRecs = false;
    }).catchError((_) { loadingRecs = false; });

    DateTime? startDate;

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
                  Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
                  const SizedBox(height: 16),
                  Text('New ${adType == 'card' ? 'Card Ad' : 'PPC Ad'}', style: GoogleFonts.spaceGrotesk(fontSize: 18, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  if (loadingRecs) const Text('Loading recommendations...', style: TextStyle(color: Colors.grey, fontSize: 12))
                  else if (recommendations != null) ...[
                    Text(
                      'Recommended CPC: ₹${recommendations!['cpc']!['min']}–₹${recommendations!['cpc']!['max']}  |  Budget: ₹${(recommendations!['weekly_budget']!['recommended'] as num).toInt()}/wk',
                      style: TextStyle(color: WeloColors.sovereignOrange, fontSize: 11, fontWeight: FontWeight.w600),
                    ),
                  ],
                  const SizedBox(height: 16),

                  if (adType == 'ppc') ...[
                    const Text('Placement', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      initialValue: placement,
                      decoration: const InputDecoration(border: OutlineInputBorder(), contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10)),
                      items: const [
                        DropdownMenuItem(value: 'listings', child: Text('Listings')),
                        DropdownMenuItem(value: 'search', child: Text('Search')),
                        DropdownMenuItem(value: 'both', child: Text('Both')),
                      ],
                      onChanged: (v) => setSheetState(() => placement = v ?? 'listings'),
                    ),
                    const SizedBox(height: 12),
                  ],

                  TextField(
                    controller: cpcCtrl,
                    decoration: InputDecoration(
                      labelText: 'Cost Per Click (₹) *',
                      hintText: recommendations != null ? 'Recommended: ₹${recommendations!['cpc']!['recommended']}' : null,
                      border: const OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 12),

                  TextField(
                    controller: weeklyBudgetCtrl,
                    decoration: InputDecoration(
                      labelText: 'Weekly Budget (₹) *',
                      hintText: recommendations != null ? 'Recommended: ₹${(recommendations!['weekly_budget']!['recommended'] as num).toInt()}' : null,
                      border: const OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 12),

                  Row(
                    children: [
                      Expanded(child: TextField(controller: durationCtrl, decoration: const InputDecoration(labelText: 'Duration (weeks) 1-8', border: OutlineInputBorder()), keyboardType: TextInputType.number)),
                      const SizedBox(width: 12),
                      Expanded(
                        child: GestureDetector(
                          onTap: () async {
                            final picked = await showDatePicker(context: ctx, initialDate: startDate ?? DateTime.now(), firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 365)));
                            if (picked != null) { startDate = picked; setSheetState(() => startCtrl.text = DateFormat('yyyy-MM-dd').format(picked)); }
                          },
                          child: AbsorbPointer(child: TextField(controller: startCtrl, decoration: const InputDecoration(labelText: 'Start Date *', border: OutlineInputBorder(), suffixIcon: Icon(Icons.calendar_today)))),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  DropdownButtonFormField<String>(
                    initialValue: targetCustomers,
                    decoration: const InputDecoration(labelText: 'Target Customers', border: OutlineInputBorder(), contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10)),
                    items: const [
                      DropdownMenuItem(value: 'all', child: Text('All Users')),
                      DropdownMenuItem(value: 'new_users', child: Text('New Users Only')),
                    ],
                    onChanged: (v) => setSheetState(() => targetCustomers = v ?? 'all'),
                  ),
                  const SizedBox(height: 12),

                  Row(
                    children: [
                      const Text('All Day'),
                      Switch(value: allDay, onChanged: (v) => setSheetState(() => allDay = v)),
                      if (!allDay) ...[
                        const SizedBox(width: 8),
                        SizedBox(width: 80, child: TextField(decoration: const InputDecoration(labelText: 'From', hintText: '09:00', border: OutlineInputBorder(), contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 8)), controller: TextEditingController(text: timeFrom), onChanged: (v) => timeFrom = v, style: const TextStyle(fontSize: 12))),
                        const SizedBox(width: 8),
                        SizedBox(width: 80, child: TextField(decoration: const InputDecoration(labelText: 'To', hintText: '22:00', border: OutlineInputBorder(), contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 8)), controller: TextEditingController(text: timeTo), onChanged: (v) => timeTo = v, style: const TextStyle(fontSize: 12))),
                      ],
                    ],
                  ),
                  const SizedBox(height: 24),

                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.black, foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      onPressed: isLoading ? null : () async {
                        if (weeklyBudgetCtrl.text.isEmpty || cpcCtrl.text.isEmpty || startCtrl.text.isEmpty) {
                          ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Fill all required fields')));
                          return;
                        }
                        setSheetState(() => isLoading = true);
                        try {
                          final weeks = int.tryParse(durationCtrl.text) ?? 1;
                          final start = DateTime.parse(startCtrl.text);
                          final end = start.add(Duration(days: weeks * 7));
                          final data = <String, dynamic>{
                            'ad_type': adType,
                            'weekly_budget': double.parse(weeklyBudgetCtrl.text),
                            'daily_budget': double.parse(weeklyBudgetCtrl.text) / 7,
                            'cpc': double.parse(cpcCtrl.text),
                            'start_date': startCtrl.text,
                            'end_date': DateFormat('yyyy-MM-dd').format(end),
                            'duration_weeks': weeks.clamp(1, 8),
                            'target_customers': targetCustomers,
                            'all_day': allDay,
                            'bid_amount': double.parse(cpcCtrl.text),
                          };
                          if (adType == 'ppc') {
                            data['placement'] = placement;
                            data['daily_budget'] = double.parse(weeklyBudgetCtrl.text) / 7;
                          }
                          if (!allDay) {
                            data['time_from'] = timeFrom;
                            data['time_to'] = timeTo;
                          }
                          await ref.read(vendorRepositoryProvider).addAd(data);
                          if (ctx.mounted) Navigator.pop(ctx);
                          _loadAds();
                        } catch (e) {
                          setSheetState(() => isLoading = false);
                          if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content: Text('Failed: $e')));
                        }
                      },
                      child: Text(isLoading ? 'Creating...' : 'Create Campaign', style: const TextStyle(fontWeight: FontWeight.bold)),
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

  Future<void> _toggleAd(dynamic ad) async {
    try {
      await ref.read(vendorRepositoryProvider).toggleAd(ad['id'].toString());
      _loadAds();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e')));
    }
  }

  Future<void> _deleteAd(dynamic ad) async {
    final confirm = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(
      title: const Text('Delete Campaign'),
      content: const Text('Are you sure?'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
        TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete', style: TextStyle(color: Colors.red))),
      ],
    ));
    if (confirm == true) {
      try { await ref.read(vendorRepositoryProvider).removeAd(ad['id'].toString()); _loadAds(); }
      catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e'))); }
    }
  }

  void _showDetails(dynamic ad) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Ad Details', style: GoogleFonts.spaceGrotesk(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 16),
            _detailRow('Type', ad['ad_type']?.toString() ?? ''),
            _detailRow('Placement', ad['placement']?.toString() ?? 'N/A'),
            _detailRow('Budget', '₹${ad['weekly_budget']}/wk'),
            _detailRow('CPC', '₹${ad['cpc']}'),
            _detailRow('Duration', '${ad['duration_weeks']} weeks (${ad['start_date'].toString().substring(0, 10)} — ${ad['end_date'].toString().substring(0, 10)})'),
            _detailRow('Target', ad['target_customers'].toString() == 'new_users' ? 'New Users' : 'All Users'),
            _detailRow('Status', ad['status']?.toString().toUpperCase() ?? ''),
            _detailRow('Ad ID', ad['id']?.toString() ?? ''),
          ],
        ),
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 100, child: Text(label, style: const TextStyle(color: Colors.black54, fontSize: 13))),
          Expanded(child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
        ],
      ),
    );
  }

  void _showPerformance(dynamic ad) async {
    showDialog(context: context, builder: (_) => const Center(child: CircularProgressIndicator()));
    try {
      final res = await ref.read(vendorRepositoryProvider).apiClient.dio.get('/vendor/ads/${ad['id']}/performance');
      final data = res.data;
      if (!mounted) return;
      Navigator.pop(context);
      showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        builder: (ctx) => Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Performance', style: GoogleFonts.spaceGrotesk(fontSize: 18, fontWeight: FontWeight.w800)),
              const SizedBox(height: 16),
              Row(children: [
                _perfCard('Impressions', '${data['totals']?['impressions'] ?? 0}'),
                const SizedBox(width: 12),
                _perfCard('Clicks', '${data['totals']?['clicks'] ?? 0}'),
              ]),
              const SizedBox(height: 12),
              Row(children: [
                _perfCard('Orders', '${data['totals']?['attributed_orders'] ?? 0}'),
                const SizedBox(width: 12),
                _perfCard('Spend', '₹${data['totals']?['spend'] ?? 0}'),
              ]),
              const SizedBox(height: 12),
              Row(children: [
                _perfCard('CTR', '${data['ctr'] ?? 0}%'),
                const SizedBox(width: 12),
                _perfCard('Conv.', '${data['conversion_rate'] ?? 0}%'),
              ]),
              const SizedBox(height: 12),
              Row(children: [
                _perfCard('CPO', '₹${data['cpo'] ?? 0}'),
                const SizedBox(width: 12),
                _perfCard('ROAS', '${data['roas'] ?? 0}x'),
              ]),
              const Divider(height: 24),
              Text('Daily data available in graph view.', style: const TextStyle(color: Colors.grey, fontSize: 12)),
            ],
          ),
        ),
      );
    } catch (e) {
      if (mounted) Navigator.pop(context);
    }
  }

  Widget _perfCard(String label, String value) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: Colors.grey.shade50, borderRadius: BorderRadius.circular(12)),
        child: Column(
          children: [
            Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: WeloColors.sovereignOrange)),
            Text(label, style: const TextStyle(color: Colors.black54, fontSize: 11)),
          ],
        ),
      ),
    );
  }
}
