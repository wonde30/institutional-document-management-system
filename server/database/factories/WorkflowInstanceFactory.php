<?php

namespace Database\Factories;

use App\Models\DocumentVersion;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkflowInstance>
 */
class WorkflowInstanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = WorkflowInstance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Try to get existing records first (seeded tests)
        $documentVersion = DocumentVersion::inRandomOrder()->first();
        $workflowVersion = WorkflowVersion::inRandomOrder()->first();
        $user = User::inRandomOrder()->first();

        // If no DocumentVersion exists, create one with a Document
        if (!$documentVersion) {
            $document = \App\Models\Document::factory()->create();
            $documentVersion = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'created_by' => $user?->id ?? User::factory()->create()->id,
                'ingestion_method' => 'digital_upload',
                'created_at' => now()->format('Y-m-d H:i:s.u'),
            ]);
        }

        // If no WorkflowVersion exists, throw informative error
        if (!$workflowVersion) {
            throw new \RuntimeException(
                'WorkflowInstanceFactory requires at least one WorkflowVersion to exist. ' .
                'Run seeders with WorkflowBaselineSeeder: php artisan db:seed --class=WorkflowBaselineSeeder'
            );
        }

        // If no User exists, create one
        if (!$user) {
            $user = User::factory()->create();
        }

        return [
            'document_version_id' => $documentVersion->id,
            'workflow_version_id' => $workflowVersion->id,
            'started_by' => $user->id,
            'started_at' => now()->format('Y-m-d H:i:s.u'),
        ];
    }

    /**
     * Indicate that the workflow instance was started by a specific user.
     */
    public function startedBy(User|int $user): static
    {
        return $this->state(fn (array $attributes) => [
            'started_by' => $user instanceof User ? $user->id : $user,
        ]);
    }

    /**
     * Indicate that the workflow instance is for a specific document version.
     */
    public function forDocumentVersion(DocumentVersion|int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'document_version_id' => $version instanceof DocumentVersion ? $version->id : $version,
        ]);
    }

    /**
     * Indicate that the workflow instance uses a specific workflow version.
     */
    public function usingWorkflowVersion(WorkflowVersion|int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'workflow_version_id' => $version instanceof WorkflowVersion ? $version->id : $version,
        ]);
    }

    /**
     * Indicate that the workflow instance has a specific parent instance.
     */
    public function childOf(WorkflowInstance|int $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_instance_id' => $parent instanceof WorkflowInstance ? $parent->id : $parent,
        ]);
    }

    /**
     * Indicate that the workflow instance was started at a specific time.
     */
    public function startedAt(string|\DateTimeInterface $timestamp): static
    {
        $formatted = $timestamp instanceof \DateTimeInterface 
            ? $timestamp->format('Y-m-d H:i:s.u')
            : $timestamp;

        return $this->state(fn (array $attributes) => [
            'started_at' => $formatted,
        ]);
    }
}
