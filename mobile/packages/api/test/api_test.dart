import 'package:flutter_test/flutter_test.dart';
import 'package:api/api.dart';

void main() {
  test('ApiClient can be instantiated', () {
    final client = ApiClient();
    expect(client, isNotNull);
  });
}
