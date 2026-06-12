<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Regression: file/image rules were pushed into the rules ARRAY as a single
 * pipe-joined string ('file|max:10240'), which Laravel does not split inside
 * arrays — it resolved to a bogus "filemax" rule that exploded with
 * BadMethodCallException the moment a file was actually attached.
 */
class FormFileValidationTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    public function test_draft_with_attached_file_saves(): void
    {
        Storage::fake('public');
        [$form, $user] = $this->makeFormWithFileField('file');

        $this->actingAsWebSession($user)
            ->post(route('forms.draft.store', $form), [
                'fields' => [
                    'title' => 'with attachment',
                    'attachment' => UploadedFile::fake()->create('cert.pdf', 100),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DocumentFormSubmission::query()->where('form_id', $form->id)->count());
    }

    public function test_draft_with_attached_image_saves(): void
    {
        Storage::fake('public');
        [$form, $user] = $this->makeFormWithFileField('image');

        $this->actingAsWebSession($user)
            ->post(route('forms.draft.store', $form), [
                'fields' => [
                    'title' => 'with image',
                    'attachment' => UploadedFile::fake()->image('photo.png'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_oversized_file_is_rejected_with_validation_error(): void
    {
        Storage::fake('public');
        [$form, $user] = $this->makeFormWithFileField('file');

        $this->actingAsWebSession($user)
            ->post(route('forms.draft.store', $form), [
                'fields' => [
                    'title' => 'too big',
                    'attachment' => UploadedFile::fake()->create('huge.pdf', 20480), // 20MB > 10MB cap
                ],
            ])
            ->assertSessionHasErrors('fields.attachment');
    }

    /** @return array{0: DocumentForm, 1: \App\Models\User} */
    private function makeFormWithFileField(string $type): array
    {
        $user = $this->makeRegularUser('filerule-'.uniqid().'@example.test');
        $form = DocumentForm::factory()->create();
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 1, 'editable_by' => ['requester'],
        ]);
        DocumentFormField::query()->create([
            'form_id' => $form->id, 'field_key' => 'attachment', 'label' => 'Attachment',
            'field_type' => $type, 'sort_order' => 2, 'editable_by' => ['requester'],
        ]);

        return [$form->fresh('fields'), $user];
    }
}
