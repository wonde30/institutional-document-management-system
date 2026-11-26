<?php

namespace Database\Factories;

use App\Models\ConfidentialityLevel;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => DocumentCategory::inRandomOrder()->first()?->id ?? 1,
            'confidentiality_id' => ConfidentialityLevel::inRandomOrder()->first()?->id ?? 1,
            'owner_unit_id' => OrganizationUnit::inRandomOrder()->first()?->id ?? 1,
            'created_by' => User::inRandomOrder()->first()?->id ?? 1,
            'custodian_id' => null,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'reference_number' => $this->generateUniqueReferenceNumber(),
            'title' => fake()->sentence(6),
            'summary' => fake()->optional(0.7)->paragraph(),
            'lifecycle' => 'active',
            'lock_version' => 0,
        ];
    }

    /**
     * Generate a unique reference number using timestamp and random suffix.
     */
    protected function generateUniqueReferenceNumber(): string
    {
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(Str::random(6));
        return "DOC-{$timestamp}-{$random}";
    }

    /**
     * Indicate that the document is retired.
     */
    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle' => 'retired',
        ]);
    }

    /**
     * Indicate that the document is tombstoned.
     */
    public function tombstoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifecycle' => 'tombstoned',
        ]);
    }

    /**
     * Set a specific category for the document.
     */
    public function forCategory(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Set a specific confidentiality level for the document.
     */
    public function withConfidentiality(int $confidentialityId): static
    {
        return $this->state(fn (array $attributes) => [
            'confidentiality_id' => $confidentialityId,
        ]);
    }

    /**
     * Set a specific owner unit for the document.
     */
    public function ownedBy(int $unitId): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_unit_id' => $unitId,
        ]);
    }

    /**
     * Set a specific creator for the document.
     */
    public function createdBy(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $userId,
        ]);
    }

    /**
     * Set a specific custodian for the document.
     */
    public function withCustodian(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'custodian_id' => $userId,
        ]);
    }

    /**
     * Set a specific document date.
     */
    public function withDocumentDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'document_date' => $date,
        ]);
    }

    /**
     * Set a specific registration timestamp.
     */
    public function registeredAt(string $datetime): static
    {
        return $this->state(fn (array $attributes) => [
            'registered_at' => $datetime,
        ]);
    }

    /**
     * Set a custom reference number.
     */
    public function withReferenceNumber(string $referenceNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_number' => $referenceNumber,
        ]);
    }

    /**
     * Set a custom title.
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $title,
        ]);
    }

    /**
     * Set a custom summary.
     */
    public function withSummary(?string $summary): static
    {
        return $this->state(fn (array $attributes) => [
            'summary' => $summary,
        ]);
    }

    /**
     * Set a specific lock version for optimistic locking tests.
     */
    public function withLockVersion(int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'lock_version' => $version,
        ]);
    }
}
