/// Result of POST /api/v1/tax/calculate.
///
/// The API response is rich (income, expenses, allowances, progressive_tax,
/// result, analysis, ...). This model keeps the raw map and exposes the few
/// headline figures the MVP screen shows, so it never breaks if the backend
/// adds fields.
class TaxCalculationResult {
  const TaxCalculationResult(this.raw);

  final Map<String, dynamic> raw;

  factory TaxCalculationResult.fromJson(Map<String, dynamic> json) =>
      TaxCalculationResult(json);

  num? get netIncome => _num(raw['net_income']);
  num? get incomeAfterExpense => _num(raw['income_after_expense']);
  num? get income => _num(raw['income']);

  /// `result` holds the final tax outcome (tax due / refund / balance).
  Map<String, dynamic>? get result =>
      raw['result'] is Map ? Map<String, dynamic>.from(raw['result']) : null;

  /// Best-effort headline "tax payable" figure from the `result` block.
  num? get taxPayable {
    final r = result;
    if (r == null) return null;
    for (final key in ['tax_due', 'net_tax', 'tax_payable', 'total_tax']) {
      final v = _num(r[key]);
      if (v != null) return v;
    }
    return null;
  }

  static num? _num(dynamic v) {
    if (v is num) return v;
    if (v is String) return num.tryParse(v);
    return null;
  }
}
