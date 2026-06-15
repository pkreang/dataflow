import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';
import '../core/auth/token_storage.dart';
import '../features/auth/login_screen.dart';
import '../features/home/home_screen.dart';
import '../features/forms/forms_list_screen.dart';
import '../features/forms/form_create_screen.dart';
import '../features/submissions/submissions_screen.dart';
import '../features/submissions/submission_detail_screen.dart';
import '../features/approvals/approvals_screen.dart';
import '../features/approvals/approval_detail_screen.dart';
import '../features/profile/profile_screen.dart';
import '../shared/widgets/main_shell.dart';

// Notifier ที่ screens เรียก notify() หลัง login/logout เพื่อให้ GoRouter re-evaluate redirect
class AuthNotifier extends ChangeNotifier {
  static final AuthNotifier instance = AuthNotifier._();
  AuthNotifier._();
  void notify() => notifyListeners();
}

final GoRouter appRouter = GoRouter(
  initialLocation: '/home',
  refreshListenable: AuthNotifier.instance,
  redirect: (context, state) {
    final token = TokenStorage.cachedToken;
    final isLoginRoute = state.matchedLocation == '/login';
    if (token == null && !isLoginRoute) return '/login';
    if (token != null && isLoginRoute) return '/home';
    return null;
  },
  routes: [
    GoRoute(
      path: '/login',
      builder: (_, _s) => const LoginScreen(),
    ),
    ShellRoute(
      builder: (context, state, child) => MainShell(child: child),
      routes: [
        GoRoute(path: '/home', builder: (_, _s) => const HomeScreen()),
        GoRoute(
          path: '/forms',
          builder: (_, _s) => const FormsListScreen(),
          routes: [
            GoRoute(
              path: ':formKey',
              builder: (_, state) => FormCreateScreen(formKey: state.pathParameters['formKey']!),
            ),
          ],
        ),
        GoRoute(
          path: '/requests',
          builder: (_, _s) => const SubmissionsScreen(),
          routes: [
            GoRoute(
              path: ':id',
              builder: (_, state) => SubmissionDetailScreen(id: int.parse(state.pathParameters['id']!)),
            ),
          ],
        ),
        GoRoute(
          path: '/approvals',
          builder: (_, _s) => const ApprovalsScreen(),
          routes: [
            GoRoute(
              path: ':id',
              builder: (_, state) => ApprovalDetailScreen(id: int.parse(state.pathParameters['id']!)),
            ),
          ],
        ),
        GoRoute(path: '/profile', builder: (_, _s) => const ProfileScreen()),
      ],
    ),
  ],
);
