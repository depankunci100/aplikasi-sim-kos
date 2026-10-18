import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/features/assets/models/asset_model.dart';

void main() {
  group('AssetModel Unit Tests', () {
    test('parses full admin/manager asset JSON correctly', () {
      final json = {
        'id': 10,
        'asset_code': 'AST-P1-202610-A1B2',
        'name': 'AC Daikin Inverter 1 PK',
        'category': 'electronics',
        'classification': 'fixed_asset',
        'condition': 'good',
        'lifecycle_status': 'active',
        'location_label': 'Kamar 101',
        'purchase_cost': 4500000.0,
        'current_book_value': 4000000.0,
        'serial_number': 'SN-998822',
        'vendor_name': 'Toko Sejuk Abadi',
        'notes': 'Unit baru garansi 3 tahun',
      };

      final model = AssetModel.fromJson(json);

      expect(model.id, 10);
      expect(model.assetCode, 'AST-P1-202610-A1B2');
      expect(model.name, 'AC Daikin Inverter 1 PK');
      expect(model.category, 'electronics');
      expect(model.condition, 'good');
      expect(model.conditionLabel, 'Baik');
      expect(model.lifecycleStatus, 'active');
      expect(model.statusLabel, 'Aktif');
      expect(model.purchaseCost, 4500000.0);
      expect(model.currentBookValue, 4000000.0);
      expect(model.locationLabel, 'Kamar 101');
    });

    test('parses tenant sanitized asset JSON where financial fields are hidden', () {
      final json = {
        'id': 15,
        'asset_code': 'AST-P1-202610-FURN',
        'name': 'Meja Belajar Minimalis',
        'category': 'furniture',
        'condition': 'slightly_damaged',
        'lifecycle_status': 'in_maintenance',
        'location_type': 'room',
        'notes': 'Sedang diperbaiki laci kanannya',
      };

      final model = AssetModel.fromJson(json);

      expect(model.id, 15);
      expect(model.name, 'Meja Belajar Minimalis');
      expect(model.condition, 'slightly_damaged');
      expect(model.conditionLabel, 'Rusak Ringan');
      expect(model.lifecycleStatus, 'in_maintenance');
      expect(model.statusLabel, 'Perawatan');
      // Nilai finansial harus null bagi penyewa
      expect(model.purchaseCost, isNull);
      expect(model.currentBookValue, isNull);
    });

    test('conditionLabel and statusLabel provide accurate localized labels', () {
      final modelBroken = AssetModel.fromJson({
        'id': 1,
        'condition': 'broken',
        'lifecycle_status': 'disposed',
      });
      expect(modelBroken.conditionLabel, 'Rusak Berat');
      expect(modelBroken.statusLabel, 'Dilepas');

      final modelMissing = AssetModel.fromJson({
        'id': 2,
        'condition': 'missing',
        'lifecycle_status': 'written_off',
      });
      expect(modelMissing.conditionLabel, 'Hilang');
      expect(modelMissing.statusLabel, 'Dihapus');
    });
  });
}
