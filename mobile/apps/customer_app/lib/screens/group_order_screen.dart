import 'package:flutter/material.dart';
import 'package:ui/ui.dart';

class GroupOrderScreen extends StatelessWidget {
  const GroupOrderScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const WeloAppBar(title: 'Group Order'),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Create or Join
            WeloCard(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  const Icon(Icons.group_add, size: 48, color: WeloColors.sovereignOrange),
                  const SizedBox(height: 12),
                  const Text('Start a Group Order', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  const Text('Order together with friends and family', style: TextStyle(color: WeloColors.textBody)),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: () {},
                      style: ElevatedButton.styleFrom(
                        backgroundColor: WeloColors.sovereignOrange,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text('Create Group Order'),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Join
            WeloCard(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Join a Group Order', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),
                  WeloTextField(hintText: 'Enter invite code'),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton(
                      onPressed: () {},
                      style: OutlinedButton.styleFrom(
                        foregroundColor: WeloColors.sovereignOrange,
                        side: const BorderSide(color: WeloColors.sovereignOrange),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text('Join'),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // How it works
            const Text('How it works', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            _buildStep(1, 'Host creates a group order and shares the invite code'),
            _buildStep(2, 'Friends join and add their items'),
            _buildStep(3, 'Host finalizes — each person pays for their own items'),
          ],
        ),
      ),
    );
  }

  Widget _buildStep(int number, String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(color: WeloColors.sovereignOrange, shape: BoxShape.circle),
            child: Center(child: Text('$number', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12))),
          ),
          const SizedBox(width: 12),
          Expanded(child: Text(text, style: const TextStyle(fontSize: 14))),
        ],
      ),
    );
  }
}
