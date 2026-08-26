/// Paginated slice returned by list endpoints.
///
/// `ApiClient._unwrap` strips the `meta` envelope, so `hasMore` is inferred
/// from the page size: a full page implies a next page exists.
class PagedResult<T> {
  const PagedResult({this.items = const [], this.hasMore = false});

  final List<T> items;
  final bool hasMore;
}
