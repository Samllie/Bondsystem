<?php

namespace App\Http\Requests\BondRequest\Concerns;

use App\Models\BondRequest;
use App\Services\BondRequestSupportingDocumentService;
use Illuminate\Validation\Validator;

trait ValidatesSupportingDocuments
{
    /**
     * @return array<string, mixed>
     */
    protected function supportingDocumentRules(): array
    {
        return [
            'supporting_documents' => ['nullable', 'array', 'max:'.BondRequestSupportingDocumentService::MAX_FILES],
            'supporting_documents.*' => [
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.BondRequestSupportingDocumentService::MAX_FILE_SIZE_KB,
            ],
            'removed_supporting_documents' => ['nullable', 'array'],
            'removed_supporting_documents.*' => ['string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function supportingDocumentMessages(): array
    {
        return [
            'supporting_documents.max' => 'You may upload at most '.BondRequestSupportingDocumentService::MAX_FILES.' supporting documents.',
            'supporting_documents.*.max' => 'Each supporting document may be at most 15 MB.',
            'supporting_documents.*.mimes' => 'Supporting documents must be PDF, JPG, JPEG, or PNG files.',
        ];
    }

    protected function validateSupportingDocumentCount(Validator $validator, ?BondRequest $bondRequest = null): void
    {
        if ($bondRequest === null) {
            return;
        }

        /** @var list<string> $existing */
        $existing = array_values(array_filter(
            $bondRequest->supporting_document_paths ?? [],
            fn ($path): bool => is_string($path) && $path !== '',
        ));

        $requestedRemovals = $this->input('removed_supporting_documents', []);
        $removedCount = is_array($requestedRemovals)
            ? count(array_intersect($requestedRemovals, $existing))
            : 0;

        $newCount = count($this->file('supporting_documents') ?? []);

        if (count($existing) - $removedCount + $newCount > BondRequestSupportingDocumentService::MAX_FILES) {
            $validator->errors()->add(
                'supporting_documents',
                'You may upload at most '.BondRequestSupportingDocumentService::MAX_FILES.' supporting documents.',
            );
        }
    }
}
