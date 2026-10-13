import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../features/auth/providers/auth_provider.dart';
import '../features/auth/screens/login_screen.dart';
import '../features/profile/screens/profile_screen.dart';
import '../features/properties/models/property_model.dart';
import '../features/properties/screens/home_screen.dart';
import '../features/properties/screens/property_detail_screen.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authProvider);

  return GoRouter(
    initialLocation: '/login',
    routes: [
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/home',
        builder: (context, state) => const HomeScreen(),
      ),
      GoRoute(
        path: '/property/:id',
        builder: (context, state) {
          final property = state.extra as PropertyModel? ??
              PropertyModel(
                id: int.tryParse(state.pathParameters['id'] ?? '1') ?? 1,
                code: 'PROP-01',
                name: 'Detail Properti',
                type: 'hybrid',
                address: 'Alamat Properti',
                city: 'Kota',
                facilities: ['WiFi', 'Parkir'],
                totalRooms: 5,
                availableRooms: 5,
              );
          return PropertyDetailScreen(property: property);
        },
      ),
      GoRoute(
        path: '/profile',
        builder: (context, state) => const ProfileScreen(),
      ),
    ],
    redirect: (context, state) {
      final isAuth = authState.isAuthenticated;
      final isLoggingIn = state.matchedLocation == '/login';

      if (!isAuth && !isLoggingIn) {
        return '/login';
      }

      if (isAuth && isLoggingIn) {
        return '/home';
      }

      return null;
    },
  );
});
