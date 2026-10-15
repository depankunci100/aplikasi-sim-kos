import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../features/auth/providers/auth_provider.dart';
import '../features/auth/screens/login_screen.dart';
import '../features/invoices/models/invoice_model.dart';
import '../features/invoices/screens/invoice_detail_screen.dart';
import '../features/invoices/screens/invoices_screen.dart';
import '../features/invoices/screens/payment_submit_screen.dart';
import '../features/inspections/models/inspection_model.dart';
import '../features/inspections/screens/inspections_screen.dart';
import '../features/inspections/screens/inspection_detail_screen.dart';
import '../features/inspections/screens/inspection_compare_screen.dart';
import '../features/profile/screens/profile_screen.dart';
import '../features/properties/models/property_model.dart';
import '../features/properties/screens/home_screen.dart';
import '../features/properties/screens/property_detail_screen.dart';
import '../features/utilities/models/utility_meter_model.dart';
import '../features/utilities/screens/utility_meters_screen.dart';
import '../features/utilities/screens/record_meter_screen.dart';
import '../features/maintenance/models/maintenance_model.dart';
import '../features/maintenance/screens/maintenance_list_screen.dart';
import '../features/maintenance/screens/maintenance_detail_screen.dart';
import '../features/maintenance/screens/maintenance_form_screen.dart';
import '../features/complaints/models/complaint_model.dart';
import '../features/complaints/screens/complaints_list_screen.dart';
import '../features/complaints/screens/complaint_detail_screen.dart';
import '../features/complaints/screens/complaint_form_screen.dart';
import '../features/housekeeping/models/housekeeping_model.dart';
import '../features/housekeeping/screens/housekeeping_list_screen.dart';
import '../features/housekeeping/screens/housekeeping_detail_screen.dart';

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
        path: '/invoices',
        builder: (context, state) => const InvoicesScreen(),
      ),
      GoRoute(
        path: '/inspections',
        builder: (context, state) => const InspectionsScreen(),
      ),
      GoRoute(
        path: '/utilities',
        builder: (context, state) => const UtilityMetersScreen(),
      ),
      GoRoute(
        path: '/utilities/record',
        builder: (context, state) {
          final meter = state.extra as UtilityMeterModel? ??
              UtilityMeterModel(
                id: 1,
                propertyId: 1,
                utilityType: 'electricity',
                meterNumber: 'PLN-DEFAULT',
                meterType: 'individual',
                unit: 'kWh',
                initialReading: 0,
                currentReading: 0,
              );
          return RecordMeterScreen(meter: meter);
        },
      ),
      GoRoute(
        path: '/maintenance',
        builder: (context, state) => const MaintenanceListScreen(),
      ),
      GoRoute(
        path: '/maintenance/new',
        builder: (context, state) => const MaintenanceFormScreen(),
      ),
      GoRoute(
        path: '/maintenance/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '1') ?? 1;
          final item = state.extra as MaintenanceRequestModel?;
          return MaintenanceDetailScreen(maintenanceId: id, initialMaintenance: item);
        },
      ),
      GoRoute(
        path: '/complaints',
        builder: (context, state) => const ComplaintsListScreen(),
      ),
      GoRoute(
        path: '/complaints/new',
        builder: (context, state) => const ComplaintFormScreen(),
      ),
      GoRoute(
        path: '/complaint/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '1') ?? 1;
          final item = state.extra as ComplaintModel?;
          return ComplaintDetailScreen(complaintId: id, initialComplaint: item);
        },
      ),
      GoRoute(
        path: '/housekeeping',
        builder: (context, state) => const HousekeepingListScreen(),
      ),
      GoRoute(
        path: '/housekeeping/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '1') ?? 1;
          final item = state.extra as HousekeepingTaskModel?;
          return HousekeepingDetailScreen(taskId: id, initialTask: item);
        },
      ),
      GoRoute(
        path: '/inspection/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '1') ?? 1;
          final inspection = state.extra as InspectionModel?;
          return InspectionDetailScreen(inspectionId: id, initialInspection: inspection);
        },
      ),
      GoRoute(
        path: '/inspection/:id/compare/:baselineId',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '1') ?? 1;
          final baselineId = int.tryParse(state.pathParameters['baselineId'] ?? '1') ?? 1;
          return InspectionCompareScreen(currentId: id, baselineId: baselineId);
        },
      ),
      GoRoute(
        path: '/invoice/:id',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '1') ?? 1;
          final invoice = state.extra as InvoiceModel?;
          return InvoiceDetailScreen(invoiceId: id, initialInvoice: invoice);
        },
      ),
      GoRoute(
        path: '/invoice/:id/pay',
        builder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '1') ?? 1;
          final invoice = state.extra as InvoiceModel?;
          return PaymentSubmitScreen(invoiceId: id, invoice: invoice);
        },
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
