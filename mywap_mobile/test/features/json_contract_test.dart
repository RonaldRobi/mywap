import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/features/events/data/models/event.dart';
import 'package:mywap_mobile/features/facilities/data/models/facility.dart';
import 'package:mywap_mobile/features/financial/data/models/financial_overview.dart';
import 'package:mywap_mobile/features/forms/data/models/form_model.dart';
import 'package:mywap_mobile/features/infaq/data/models/infaq.dart';
import 'package:mywap_mobile/features/member/data/models/announcement.dart';
import 'package:mywap_mobile/features/member/data/models/fee_status.dart';
import 'package:mywap_mobile/features/polls/data/models/poll.dart';

/// Laravel's `decimal:2` cast serializes values as strings (`"0.00"`), and
/// some serializers may emit booleans as `0`/`1`. These models must parse both
/// forms without throwing, because a thrown cast error is not an ApiException
/// and surfaces to the user as a generic "unexpected error" screen.
void main() {
  group('events', () {
    test('RegistrationForm parses a string decimal price', () {
      final form = RegistrationForm.fromJson({
        'id': 1,
        'title': 'Peserta',
        'price': '0.00',
        'payment_required': false,
      });

      expect(form.price, 0.0);
    });

    test('EventDetail parses registration forms that carry string prices', () {
      final detail = EventDetail.fromJson({
        'event': {'id': 10, 'title': 'Muktamar'},
        'registrationForms': [
          {'id': 1, 'title': 'Peserta', 'price': '15.50'},
        ],
        'relatedEvents': [],
        'myRegistration': null,
      });

      expect(detail.registration_forms, isNotNull);
      expect(detail.registration_forms!.single.price, 15.5);
    });
  });

  group('forms', () {
    test('FormModel parses string price and numeric boolean', () {
      final form = FormModel.fromJson({
        'id': '7',
        'title': 'Pendaftaran',
        'price': '10.00',
        'payment_required': 1,
      });

      expect(form.id, 7);
      expect(form.price, 10.0);
      expect(form.paymentRequired, isTrue);
    });
  });

  group('infaq', () {
    test('Infaq parses string amounts and numeric boolean', () {
      final infaq = Infaq.fromJson({
        'id': '3',
        'target_amount': '1000.00',
        'collected_amount': '500.50',
        'progress_percent': '50',
        'is_external': 0,
      });

      expect(infaq.id, 3);
      expect(infaq.targetAmount, 1000.0);
      expect(infaq.collectedAmount, 500.5);
      expect(infaq.progressPercent, 50.0);
      expect(infaq.isExternal, isFalse);
    });
  });

  group('financial', () {
    test('FinancialCampaign parses string amounts', () {
      final campaign = FinancialCampaign.fromJson({
        'id': '1',
        'target_amount': '100.00',
        'current_amount': '25.50',
        'progress_percent': '25',
      });

      expect(campaign.id, 1);
      expect(campaign.target_amount, 100.0);
      expect(campaign.current_amount, 25.5);
      expect(campaign.progress_percent, 25);
    });

    test('FeeStatusSummary parses a string amount_due', () {
      final fee = FeeStatusSummary.fromJson({
        'status': 'due',
        'amount_due': '50.00',
      });

      expect(fee.amount_due, 50.0);
    });
  });

  group('member fee status', () {
    test('FeeStatus parses nested string amounts', () {
      final fee = FeeStatus.fromJson({
        'status': {'status': 'due', 'amount_due': '50.00'},
        'fee_amount': '50.00',
      });

      expect(fee.amount_due, 50.0);
      expect(fee.fee_amount, 50.0);
    });
  });

  group('facilities', () {
    test('Facility parses string prices and numeric boolean', () {
      final facility = Facility.fromJson({
        'id': '4',
        'price_per_unit': '12.50',
        'member_price_per_unit': '10.00',
        'capacity': '20',
        'is_active': 1,
      });

      expect(facility.id, 4);
      expect(facility.pricePerUnit, 12.5);
      expect(facility.memberPricePerUnit, 10.0);
      expect(facility.capacity, 20);
      expect(facility.isActive, isTrue);
    });
  });

  group('polls & announcements', () {
    test('PollResultOption parses string percentages', () {
      final option = PollResultOption.fromJson({
        'id': '2',
        'count': '3',
        'percentage': '50.5',
        'width_pct': '50.5',
      });

      expect(option.count, 3);
      expect(option.percentage, 50.5);
      expect(option.widthPct, 50.5);
    });

    test('Announcement parses string counts and numeric boolean', () {
      final announcement = Announcement.fromJson({
        'id': '9',
        'likes_count': '7',
        'reads_count': '11',
        'is_pinned': 1,
        'is_read': 0,
      });

      expect(announcement.id, 9);
      expect(announcement.likes_count, 7);
      expect(announcement.reads_count, 11);
      expect(announcement.is_pinned, isTrue);
      expect(announcement.is_read, isFalse);
    });
  });
}
