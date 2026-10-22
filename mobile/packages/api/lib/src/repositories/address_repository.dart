import '../api_client.dart';
import '../models/address.dart';

class AddressRepository {
  final ApiClient apiClient;

  AddressRepository(this.apiClient);

  Future<List<Address>> getAddresses() async {
    final response = await apiClient.dio.get('/addresses');
    final data = response.data;
    // Backend may return { addresses: [...] } or a plain list
    final List<dynamic> list = data is List
        ? data
        : (data['addresses'] ?? data['data'] ?? []);
    return list.map((json) => Address.fromJson(json)).toList();
  }

  Future<Address> storeAddress(Map<String, dynamic> data) async {
    final response = await apiClient.dio.post('/addresses', data: data);
    final body = response.data;
    // Backend may return { address: {...} } or plain object
    final json = body['address'] ?? body;
    return Address.fromJson(json);
  }

  Future<Address> updateAddress(String id, Map<String, dynamic> data) async {
    final response = await apiClient.dio.put('/addresses/$id', data: data);
    final body = response.data;
    final json = body['address'] ?? body;
    return Address.fromJson(json);
  }

  Future<bool> destroyAddress(String id) async {
    final response = await apiClient.dio.delete('/addresses/$id');
    return response.statusCode == 200 || response.statusCode == 204;
  }

  Future<Address> setDefault(String id) async {
    final response = await apiClient.dio.patch('/addresses/$id/default');
    final body = response.data;
    final json = body['address'] ?? body;
    return Address.fromJson(json);
  }
}
