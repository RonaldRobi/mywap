import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/parsing.dart';
import 'models/product.dart';
import 'paged_result.dart';

class ProductRepository {
  ProductRepository(this._api);

  final ApiClient _api;

  /// Page size (must be in the backend whitelist: 12/25/50/100).
  static const int perPage = 25;

  Future<PagedResult<Product>> products({
    int page = 1,
    String? search,
    int? categoryId,
    String sort = 'latest',
  }) async {
    final data = await _api.get(
      ApiPaths.products,
      query: {
        'page': page,
        'per_page': perPage,
        'sort': sort,
        if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
        if (categoryId != null) 'category_id': categoryId,
      },
    );
    final items = parseList<Product>(data, Product.fromJson);
    return PagedResult(items: items, hasMore: items.length >= perPage);
  }

  Future<ProductDetail> productDetail(int id) async {
    final data = await _api.get(ApiPaths.productDetail(id));
    return ProductDetail.fromJson(
      data is Map<String, dynamic> ? data : const {},
    );
  }

  /// All categories (endpoint paginates 20/page; loads every page).
  Future<List<Category>> categories() async {
    final result = <Category>[];
    var page = 1;
    while (true) {
      final data = await _api.get(
        ApiPaths.categories,
        query: {'page': page, 'per_page': 100},
      );
      final items = parseList<Category>(data, Category.fromJson);
      result.addAll(items);
      if (items.length < 20) break;
      page++;
    }
    return result;
  }
}
