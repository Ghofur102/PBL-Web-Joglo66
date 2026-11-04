<?php

namespace Tests\Unit\Services;

use App\Enums\BookingDetailStatus;
use App\Enums\CancelRefundStatus;
use App\Enums\GeneralStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RentalStatus;
use App\Enums\UserRole;

final class ServiceTestData
{
    public const DATE_20_OCT_2026 = '2026-10-20';
    public const DATE_21_OCT_2026 = '2026-10-21';
    public const DATE_22_OCT_2026 = '2026-10-22';
    public const DATE_23_OCT_2026 = '2026-10-23';
    public const DATE_24_OCT_2026 = '2026-10-24';
    public const DATE_25_OCT_2026 = '2026-10-25';
    public const DATE_26_OCT_2026 = '2026-10-26';

    public const TIME_10_00 = '10:00:00';
    public const TIME_11_00 = '11:00:00';
    public const TIME_09_00 = '09:00:00';
    public const TIME_12_00 = '12:00:00';
    public const TIME_13_00 = '13:00:00';
    public const TIME_14_00 = '14:00:00';
    public const TIME_15_00 = '15:00:00';
    public const TIME_16_00 = '16:00:00';
    public const TIME_17_00 = '17:00:00';
    public const TIME_18_00 = '18:00:00';
    public const TIME_14_35 = '14:35:00';
    public const TIME_10_30 = '10:30:00';
    public const TIME_11_30 = '11:30:00';
    public const TIME_10_30_SHORT = '10:30';
    public const TIME_11_30_SHORT = '11:30';

    public const ZERO = 0;
    public const ONE = 1;
    public const TWO = 2;
    public const THREE = 3;
    public const FOUR = 4;
    public const FIVE = 5;
    public const TEN = 10;
    public const FIFTY = 50;

    public const ONE_THOUSAND = 1000;
    public const TWO_THOUSAND = 2000;
    public const TWO_THOUSAND_FIVE_HUNDRED = 2500;
    public const THREE_THOUSAND = 3000;
    public const FIFTEEN_THOUSAND = 15000;
    public const EIGHTEEN_THOUSAND = 18000;
    public const ONE_HUNDRED_THOUSAND = 100000;

    public const PHONE_PRIMARY = '081111111111';
    public const PHONE_SECONDARY = '081234567890';
    public const PHONE_TERTIARY = '089876543210';
    public const PHONE_ALTERNATIVE = '082222222222';
    public const PHONE_CONTACT = '083333333333';
    public const PHONE_OTHER = '084444444444';

    public const CUSTOMER_BUDI = 'Budi';
    public const CUSTOMER_RINA = 'Rina';
    public const CUSTOMER_DINA = 'Dina';
    public const CUSTOMER_SITI = 'Siti';
    public const CUSTOMER_PRIMARY = 'Dina Utama';
    public const CUSTOMER_MATCHING = 'Dina Sesuai';
    public const CUSTOMER_LATEST = 'Dina Terakhir';
    public const CUSTOMER_OTHER = 'Dina Lain';

    public const FIELD_PRIMARY = 'Lapangan Utama';
    public const FIELD_SECONDARY = 'Lapangan Lain';

    public const TEAM_PRIMARY = 'Tim Pagi';
    public const TEAM_SECONDARY = 'Tim Sore';

    public const ATTRIBUTE_BALL = 'Bola';
    public const ATTRIBUTE_VEST = 'Rompi';
    public const ATTRIBUTE_INACTIVE = 'Atribut Nonaktif';

    public const TYPE_ATTRIBUTE_PERALATAN = 'peralatan';
    public const TYPE_ATTRIBUTE_PAKAIAN = 'pakaian';

    public const EMAIL_OWNER = 'owner';
    public const EMAIL_TENANT_BUDI = 'tenant-budi';
    public const EMAIL_TENANT_RINA = 'tenant-rina';
    public const EMAIL_TENANT_SITI = 'tenant-siti';
    public const EMAIL_TENANT_DINA = 'tenant-dina';

    public const FIVE_HUNDRED = 500;

    public const MESSAGE_INSUFFICIENT_STOCK = 'Stok tidak mencukupi. Sisa stok Rompi: 3';
    public const MESSAGE_INACTIVE_ATTRIBUTE = 'Atribut Atribut Nonaktif sedang tidak aktif.';
    public const MESSAGE_ALREADY_RETURNED = 'Atribut ini sudah dikembalikan.';
    public const MESSAGE_TEST_INSUFFICIENT_STOCK = 'Rental dengan stok tidak cukup seharusnya ditolak.';
    public const MESSAGE_TEST_INACTIVE_ATTRIBUTE = 'Rental atribut nonaktif seharusnya ditolak.';
    public const MESSAGE_TEST_ALREADY_RETURNED = 'Rental yang sudah dikembalikan seharusnya ditolak.';
    public const MESSAGE_WARNING_SECURE = 'Peringatan Keamanan';

    public const ROLE_OWNER = UserRole::OWNER->value;
    public const ROLE_TENANT = UserRole::TENANT->value;
    public const ROLE_ADMIN = UserRole::ADMIN->value;

    public const GENERAL_ACTIVE = GeneralStatus::ACTIVE->value;
    public const GENERAL_INACTIVE = GeneralStatus::INACTIVE->value;

    public const BOOKING_ACTIVE = BookingDetailStatus::ACTIVE->value;
    public const BOOKING_WAITING = BookingDetailStatus::WAITING->value;
    public const BOOKING_FINISH = BookingDetailStatus::FINISH->value;
    public const BOOKING_CANCELLED = BookingDetailStatus::CANCELLED->value;

    public const RENTAL_BORROWED = RentalStatus::BORROWED->value;
    public const RENTAL_RETURNED = RentalStatus::RETURNED->value;
    public const RENTAL_OVERDUE = RentalStatus::OVERDUE->value;

    public const APPROVAL_PENDING = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';
    public const CANCEL_REFUND_NONE = CancelRefundStatus::NONE->value;
    public const CANCEL_REFUND_FULL = CancelRefundStatus::FULL->value;
    public const CANCEL_REFUND_PARTIAL = CancelRefundStatus::PARTIAL->value;
    public const PAYMENT_DOWN_PAYMENT = PaymentType::DOWN_PAYMENT->value;
    public const PAYMENT_PENDING = PaymentStatus::PENDING->value;
    public const PAYMENT_SUCCESS = PaymentStatus::SUCCESS->value;
    public const PAYMENT_FAILED = PaymentStatus::FAILED->value;
    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_TYPE_REFUND = PaymentType::REFUND->value;

    public const CANCEL_REASON = 'Lapangan tidak dapat digunakan';
    public const REJECT_REASON = 'Bukti pembatalan belum lengkap';
    public const REFERENCE_PREFIX = 'CNL-REF-';

    public const PAYMENT_REFERENCE = 'CSH-TEST-REFERENCE';
    public const PAYMENT_METHOD = 'cash';
    public const NOTIFICATION_TITLE = 'Pembatalan';
    public const NOTIFICATION_MESSAGE = 'Pengajuan pembatalan';
    public const NOTIFICATION_READ_TIMESTAMP = '2026-10-31 12:00:00';
    public const REPORT_MONTH = 10;
    public const REPORT_YEAR = 2026;
    public const REPORT_TOKEN = 'report-token';
    public const REPORT_PDF_CONTENT = 'pdf-content';

    public static function dateTime(string $date, string $time): string
    {
        return $date.' '.$time;
    }
}
