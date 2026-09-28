class ApiConfig {
  static const String baseUrl = String.fromEnvironment('API_BASE_URL',
      defaultValue: 'http://10.0.2.2:8000/api/v1');

  static String? imageUrl(String? value) {
    if (value == null || value.trim().isEmpty) return null;
    final base = Uri.parse(baseUrl);
    final uri = Uri.tryParse(value.trim());
    if (uri == null) return null;
    if (uri.hasScheme) {
      if (uri.scheme != 'http' && uri.scheme != 'https') return null;
      if (['localhost', '127.0.0.1', '10.0.2.2'].contains(uri.host)) {
        return uri
            .replace(scheme: base.scheme, host: base.host, port: base.port)
            .toString();
      }
      return uri.toString();
    }
    if (uri.hasAuthority) return null;
    final path = value.startsWith('/')
        ? value
        : value.startsWith('storage/')
            ? '/$value'
            : '/storage/$value';
    return base.replace(path: path, query: null, fragment: null).toString();
  }

  static Uri website(String path) =>
      Uri.parse(baseUrl).replace(path: path, query: null);
}
