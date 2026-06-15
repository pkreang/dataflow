import 'package:flutter/material.dart';
import 'router/app_router.dart';
import 'shared/theme/app_theme.dart';
import 'core/auth/token_storage.dart';
import 'core/push/push_notification_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await TokenStorage.init();
  try {
    await PushNotificationService.initialize();
  } catch (_) {}
  runApp(const DataFlowApp());
}

class DataFlowApp extends StatelessWidget {
  const DataFlowApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'DataFlow',
      theme: AppTheme.light,
      routerConfig: appRouter,
      debugShowCheckedModeBanner: false,
    );
  }
}
