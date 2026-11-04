import 'dart:convert';
import 'dart:io';
import 'package:integration_test/integration_test_driver_extended.dart';

Future<void> main() async {
  final output = Directory('../storage/app/mobile-demo-validation/screenshots');
  await output.create(recursive: true);
  await integrationDriver(
    onScreenshot: (name, bytes, [args]) async {
      await File('${output.path}/$name.png').writeAsBytes(bytes);
      return true;
    },
    writeResponseOnFailure: true,
    responseDataCallback: (data) async {
      // Contains disposable account email/order numbers, never bearer tokens or passwords.
      final safe = Map<String, dynamic>.from(data ?? {})..remove('screenshots');
      final proof = Platform.environment['DEMO_TRACKING'] == '1'
          ? 'device-tracking-proof.json'
          : Platform.environment['DEMO_ADDITIONAL'] == '1'
              ? 'device-extra-proof.json'
              : 'device-proof.json';
      await File('../storage/app/mobile-demo-validation/$proof')
          .writeAsString(const JsonEncoder.withIndent('  ').convert(safe));
    },
  );
}
