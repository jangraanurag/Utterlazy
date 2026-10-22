String? sanitizeImageUrl(String? url) {
  if (url == null) return null;
  final trimmed = url.trim();
  if (trimmed.isEmpty) return null;
  // Relative path (e.g. /images/foo.png) → resolve against the API host.
  if (trimmed.startsWith('/')) {
    return 'https://catalog.utterlazy.com$trimmed';
  }
  return trimmed
      .replaceAll(RegExp(r'^http://localhost:8001', caseSensitive: false), 'https://catalog.utterlazy.com')
      .replaceAll(RegExp(r'^http://localhost:8000', caseSensitive: false), 'https://catalog.utterlazy.com')
      .replaceAll(RegExp(r'^http://127\.0\.0\.1:8001', caseSensitive: false), 'https://catalog.utterlazy.com')
      .replaceAll(RegExp(r'^http://127\.0\.0\.1:8000', caseSensitive: false), 'https://catalog.utterlazy.com');
}
