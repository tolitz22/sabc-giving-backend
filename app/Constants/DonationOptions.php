<?php

namespace App\Constants;

final class DonationOptions
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CARD = 'card';
    public const METHOD_EWALLET = 'ewallet';

    public const CATEGORIES = [
        'Tithes & Offering',
        'Missions',
        'Benevolence',
        'Building Fund',
        'Other',
    ];

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
    ];

    public const METHODS = [
        self::METHOD_BANK_TRANSFER,
        self::METHOD_CARD,
        self::METHOD_EWALLET,
    ];
}
