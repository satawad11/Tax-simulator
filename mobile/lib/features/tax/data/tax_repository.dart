import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import 'tax_models.dart';

/// Talks to the public tax endpoints. No auth required for calculate/plan.
class TaxRepository {
  TaxRepository(this._api);

  final ApiClient _api;

  /// POST /api/v1/tax/calculate
  ///
  /// Minimal salary case (มาตรา 40(1), ภ.ง.ด.91). Extend [incomes]/[allowances]
  /// as the form grows; income_type codes come from
  /// GET /tax-years/{year}/income-types.
  Future<TaxCalculationResult> calculateSalary({
    required int taxYear,
    required num grossSalary,
    String formCode = 'PND91',
    List<Map<String, dynamic>> allowances = const [],
  }) async {
    final data = await _api.post('/tax/calculate', body: {
      'tax_year': taxYear,
      'form_code': formCode,
      'incomes': [
        {
          'income_type': 'SECTION_40_1',
          'gross_amount': grossSalary,
        },
      ],
      if (allowances.isNotEmpty) 'allowances': allowances,
    });
    return TaxCalculationResult.fromJson(data as Map<String, dynamic>);
  }
}

final taxRepositoryProvider = Provider<TaxRepository>((ref) {
  return TaxRepository(ref.watch(apiClientProvider));
});
