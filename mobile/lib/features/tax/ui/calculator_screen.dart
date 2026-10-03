import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../data/tax_models.dart';
import '../data/tax_repository.dart';

/// Guest tax calculator — the app's headline feature, usable without login.
/// A deliberately minimal salary (มาตรา 40(1)) case that proves the API path
/// end to end; the full multi-income form is built out in Milestone 3.
class CalculatorScreen extends ConsumerStatefulWidget {
  const CalculatorScreen({super.key});

  @override
  ConsumerState<CalculatorScreen> createState() => _CalculatorScreenState();
}

class _CalculatorScreenState extends ConsumerState<CalculatorScreen> {
  static const int _defaultTaxYear = 2568; // active year per the backend seed

  final _salary = TextEditingController();
  bool _loading = false;
  String? _error;
  TaxCalculationResult? _result;

  @override
  void dispose() {
    _salary.dispose();
    super.dispose();
  }

  Future<void> _calculate() async {
    final gross = num.tryParse(_salary.text.replaceAll(',', '').trim());
    if (gross == null || gross < 0) {
      setState(() => _error = 'กรอกเงินเดือน/เงินได้ทั้งปีให้ถูกต้อง');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
      _result = null;
    });
    try {
      final result = await ref.read(taxRepositoryProvider).calculateSalary(
            taxYear: _defaultTaxYear,
            grossSalary: gross,
          );
      setState(() => _result = result);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'คำนวณไม่สำเร็จ กรุณาลองใหม่');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('คำนวณภาษี (ภ.ง.ด.91)')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'เงินได้ประเภทเงินเดือน มาตรา 40(1) ปีภาษี $_defaultTaxYear',
                style: TextStyle(color: AppColors.inkMuted),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _salary,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')),
                ],
                decoration: const InputDecoration(
                  labelText: 'เงินได้ทั้งปี (บาท)',
                  prefixIcon: Icon(Icons.payments_outlined),
                ),
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _loading ? null : _calculate,
                child: _loading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white),
                      )
                    : const Text('คำนวณ'),
              ),
              if (_error != null) ...[
                const SizedBox(height: 16),
                Text(_error!, style: const TextStyle(color: AppColors.danger)),
              ],
              if (_result != null) ...[
                const SizedBox(height: 24),
                _ResultCard(result: _result!),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ResultCard extends StatelessWidget {
  const _ResultCard({required this.result});

  final TaxCalculationResult result;

  String _fmt(num? v) =>
      v == null ? '-' : v.toStringAsFixed(2).replaceAllMapped(
          RegExp(r'\B(?=(\d{3})+(?!\d))'), (m) => ',');

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('ผลการคำนวณ',
              style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink)),
          const SizedBox(height: 12),
          _row('เงินได้', _fmt(result.income)),
          _row('เงินได้หลังหักค่าใช้จ่าย', _fmt(result.incomeAfterExpense)),
          _row('เงินได้สุทธิ', _fmt(result.netIncome)),
          const Divider(height: 24),
          _row('ภาษีที่ต้องชำระ (บาท)', _fmt(result.taxPayable), strong: true),
          const SizedBox(height: 12),
          const Text(
            'ตัวเลขคำนวณโดยเซิร์ฟเวอร์เป็นข้อมูลอ้างอิงเบื้องต้น',
            style: TextStyle(fontSize: 12, color: AppColors.inkSubtle),
          ),
        ],
      ),
    );
  }

  Widget _row(String label, String value, {bool strong = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppColors.inkMuted)),
          Text(
            value,
            style: TextStyle(
              fontWeight: strong ? FontWeight.w700 : FontWeight.w500,
              color: strong ? AppColors.accent : AppColors.ink,
              fontSize: strong ? 18 : 15,
            ),
          ),
        ],
      ),
    );
  }
}
