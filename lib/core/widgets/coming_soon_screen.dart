import 'package:flutter/material.dart';

/// Placeholder body for feature screens not yet implemented. Each real
/// screen replaces this with its own body once that feature is built.
class ComingSoonScreen extends StatelessWidget {
  const ComingSoonScreen({super.key, required this.title, required this.icon});

  final String title;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text('$title is coming soon', style: TextStyle(color: Colors.grey.shade600)),
          ],
        ),
      ),
    );
  }
}
