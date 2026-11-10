// Backend `decimal:2` casts serialize numbers as strings (e.g. `"50.00"`),
// so numeric fields are parsed tolerantly. The helpers now live in
// core/utils/json_parsing.dart and are re-exported here so existing
// ecommerce imports keep working.
export '../../../../core/utils/json_parsing.dart';
