import 'package:google_sign_in/google_sign_in.dart';
import 'package:flutter/foundation.dart';

class GoogleAuthException implements Exception {
  final String message;
  const GoogleAuthException(this.message);

  @override
  String toString() => message;
}

class GoogleAuthService {
  static const _serverClientId =
      String.fromEnvironment('GOOGLE_SERVER_CLIENT_ID');
  static final _signIn = GoogleSignIn.instance;
  static Future<void>? _initialization;
  static bool get isConfigured => _serverClientId.trim().isNotEmpty;
  static bool get isSupported =>
      isConfigured &&
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  Future<String> obtainIdToken() async {
    if (_serverClientId.trim().isEmpty) {
      throw const GoogleAuthException(
          'Google sign-in is not configured for this build.');
    }

    try {
      await (_initialization ??=
          _signIn.initialize(serverClientId: _serverClientId));
      if (!_signIn.supportsAuthenticate()) {
        throw const GoogleAuthException(
            'Google sign-in is unavailable on this device.');
      }
      final account = await _signIn.authenticate();
      final idToken = account.authentication.idToken;
      if (idToken == null || idToken.trim().isEmpty) {
        throw const GoogleAuthException(
            'Google did not return a secure identity token. Please try again.');
      }
      return idToken;
    } on GoogleSignInException catch (error) {
      if (error.code == GoogleSignInExceptionCode.canceled) {
        throw const GoogleAuthException('Google sign-in was cancelled.');
      }
      if (error.code == GoogleSignInExceptionCode.clientConfigurationError ||
          error.code == GoogleSignInExceptionCode.providerConfigurationError) {
        throw const GoogleAuthException(
            'Google sign-in is not configured correctly for this app.');
      }
      throw const GoogleAuthException(
          'Unable to contact Google. Check your connection and try again.');
    } on GoogleAuthException {
      rethrow;
    } catch (_) {
      throw const GoogleAuthException(
          'Unable to contact Google. Check your connection and try again.');
    }
  }
}
