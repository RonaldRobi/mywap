import 'package:flutter_test/flutter_test.dart';
import 'package:mywap_mobile/core/deeplink/deep_links.dart';

void main() {
  group('deepLinkPathOf', () {
    test('https URL kehadiran', () {
      expect(
        deepLinkPathOf(Uri.parse('https://mywap.my/events/5/attend/abc123')),
        '/events/5/attend/abc123',
      );
    });

    test('skim tersuai dengan hos', () {
      expect(
        deepLinkPathOf(Uri.parse('mywap://mywap.my/kad/PKPIM-0001')),
        '/kad/PKPIM-0001',
      );
    });

    test('laluan akar', () {
      expect(deepLinkPathOf(Uri.parse('https://mywap.my/')), '/');
    });

    test('kekal query string', () {
      expect(
        deepLinkPathOf(Uri.parse('https://mywap.my/events/5/attend/t?x=1')),
        '/events/5/attend/t?x=1',
      );
    });
  });
}
