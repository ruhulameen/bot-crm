<?php

namespace Database\Factories;

use App\Enums\LeadType;
use App\Models\EmailLead;
use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLead>
 */
class EmailLeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => '+9199'.fake()->unique()->numerify('########'),
            'password' => fake()->bothify('??##??##'),
            'type' => fake()->randomElement(LeadType::cases()),
            'number_range' => '',
            'source' => 'manual',
        ];
    }

    public function ofType(LeadType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function inRange(string $range): static
    {
        return $this->state(fn () => ['number_range' => $range]);
    }

    /**
     * Supplied by a bot account, as the importer and the API tag it.
     */
    public function suppliedBy(License $license, string $source = 'api'): static
    {
        return $this->state(fn () => [...EmailLead::tagsFor($license), 'source' => $source]);
    }
}
