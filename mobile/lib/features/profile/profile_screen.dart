import 'package:flutter/material.dart';
import '../../core/auth/auth_service.dart';
import '../../core/auth/token_storage.dart';
import '../../router/app_router.dart';
import '../../shared/theme/app_theme.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  Map<String, dynamic>? _user;

  @override
  void initState() {
    super.initState();
    _loadUser();
  }

  Future<void> _loadUser() async {
    final user = await TokenStorage.getUserJson();
    if (user != null && mounted) {
      try {
        final map = await AuthService().cachedUser();
        setState(() => _user = map);
      } catch (_) {}
    }
  }

  Future<void> _logout() async {
    await AuthService().logout();
    appRouter.refresh();
  }

  @override
  Widget build(BuildContext context) {
    final name = '${_user?['first_name'] ?? ''} ${_user?['last_name'] ?? ''}'.trim();
    final email = _user?['email'] as String? ?? '';
    final dept = _user?['department'] as String? ?? '';

    return Scaffold(
      appBar: AppBar(title: const Text('โปรไฟล์')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            const SizedBox(height: 16),
            CircleAvatar(
              radius: 40,
              backgroundColor: AppTheme.primary,
              child: Text(name.isNotEmpty ? name[0].toUpperCase() : '?', style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold)),
            ),
            const SizedBox(height: 12),
            Text(name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            if (email.isNotEmpty) Text(email, style: const TextStyle(color: AppTheme.muted)),
            if (dept.isNotEmpty) Text(dept, style: const TextStyle(color: AppTheme.muted, fontSize: 13)),
            const SizedBox(height: 24),
            Card(
              child: Column(
                children: [
                  ListTile(leading: const Icon(Icons.info_outline), title: const Text('เวอร์ชัน'), trailing: const Text('1.0.0', style: TextStyle(color: AppTheme.muted))),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.logout, color: Color(0xFFEF4444)),
                    title: const Text('ออกจากระบบ', style: TextStyle(color: Color(0xFFEF4444))),
                    onTap: () => showDialog(
                      context: context,
                      builder: (dialogContext) => AlertDialog(
                        title: const Text('ออกจากระบบ'),
                        content: const Text('ต้องการออกจากระบบใช่ไหม?'),
                        actions: [
                          TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('ยกเลิก')),
                          ElevatedButton(onPressed: () { Navigator.pop(dialogContext); _logout(); }, style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFEF4444)), child: const Text('ออกจากระบบ')),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
