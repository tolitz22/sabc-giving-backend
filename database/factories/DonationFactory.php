<?php

namespace Database\Factories;

use App\Constants\DonationOptions;
use App\Models\Donation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Donation> */
class DonationFactory extends Factory
{
    protected $model = Donation::class;

    public function definition(): array
    {
        return [
            'donor_name' => fake()->name(),
            'donor_email' => fake()->safeEmail(),
            'donor_mobile' => '09'.fake()->numerify('#########'),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'category' => fake()->randomElement(DonationOptions::CATEGORIES),
            'giving_method' => fake()->randomElement(DonationOptions::METHODS),
            'status' => DonationOptions::STATUS_PENDING,
            'note' => fake()->optional()->sentence(),
            'metadata' => [],
        ];
    }

    public function bankTransferUnderReview(): self
    {
        return $this->state(fn () => [
            'giving_method' => DonationOptions::METHOD_BANK_TRANSFER,
            'status' => DonationOptions::STATUS_UNDER_REVIEW,
            'gateway_reference' => fake()->bothify('BT-####??'),
            'metadata' => [
                'bank_name' => 'BDO',
                'transfer_date' => now()->toDateString(),
            ],
        ]);
    }

    public function paid(): self
    {
        return $this->state(fn () => [
            'status' => DonationOptions::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}
