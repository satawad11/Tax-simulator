import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme/app_theme.dart';
import '../auth/state/auth_controller.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final name = auth is AuthAuthenticated ? auth.user.name : '';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tax Simulator'),
        actions: [
          IconButton(
            tooltip: 'ออกจากระบบ',
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'สวัสดี $name',
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w700,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 4),
            const Text('เริ่มวางแผนภาษีของคุณ',
                style: TextStyle(color: AppColors.inkMuted)),
            const SizedBox(height: 24),
            _MenuCard(
              icon: Icons.calculate_outlined,
              title: 'คำนวณภาษี',
              subtitle: 'ประเมินภาษีเงินได้บุคคลธรรมดา',
              onTap: () => context.push('/calculator'),
            ),
            const SizedBox(height: 12),
            _MenuCard(
              icon: Icons.folder_copy_outlined,
              title: 'แบบยื่นของฉัน',
              subtitle: 'จัดการแบบยื่นภาษี (เร็ว ๆ นี้)',
              onTap: () {},
              enabled: false,
            ),
            const SizedBox(height: 12),
            _MenuCard(
              icon: Icons.article_outlined,
              title: 'บทความ & ความรู้',
              subtitle: 'อ่านเรื่องภาษีที่ควรรู้ (เร็ว ๆ นี้)',
              onTap: () {},
              enabled: false,
            ),
          ],
        ),
      ),
    );
  }
}

class _MenuCard extends StatelessWidget {
  const _MenuCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.enabled = true,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: enabled ? 1 : 0.5,
      child: Material(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: enabled ? onTap : null,
          child: Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.line),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceSoft,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: AppColors.accent),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title,
                          style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                              color: AppColors.ink)),
                      const SizedBox(height: 2),
                      Text(subtitle,
                          style: const TextStyle(
                              fontSize: 13, color: AppColors.inkSubtle)),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right, color: AppColors.inkSubtle),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
