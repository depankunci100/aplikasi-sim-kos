import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'core/constants/api_endpoints.dart';
import 'core/theme/app_theme.dart';
import 'routing/app_router.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();
  final savedUrl = prefs.getString('api_base_url');
  if (savedUrl != null && savedUrl.isNotEmpty) {
    ApiEndpoints.setCustomBaseUrl(savedUrl);
  }

  runApp(
    const ProviderScope(
      child: SimKosApp(),
    ),
  );
}

class SimKosApp extends ConsumerWidget {
  const SimKosApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'SIM Kos & Guesthouse',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      routerConfig: router,
    );
  }
}
