<?php

namespace App\Services;

use App\Models\BondRequest;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BondRequestSupportingDocumentService
{
    public const MAX_FILES = 5;

    public const MAX_FILE_SIZE_KB = 15360;

    public function disk(): Filesystem
    {
        return Storage::disk('public');
    }

    public function storageDirectory(BondRequest $bondRequest): string
    {
        $now = now();

        return sprintf(
            'supporting-documents/%s/%s/request_%d',
            $now->format('Y'),
            $now->format('m'),
            $bondRequest->id,
        );
    }

    /**
     * @return array<int, array{path: string, url: string, name: string}>
     */
    public function documentsFor(BondRequest $bondRequest): array
    {
        return collect($bondRequest->supporting_document_paths ?? [])
            ->filter(fn ($path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): array => [
                'path' => $path,
                'url' => $this->disk()->url($path),
                'name' => basename($path),
            ])
            ->values()
            ->all();
    }

    /**
     * Persist uploaded files to disk and return the updated path list.
     *
     * @return list<string>
     */
    public function syncFromRequest(Request $request, BondRequest $bondRequest): array
    {
        /** @var list<string> $paths */
        $paths = array_values(array_filter(
            $bondRequest->supporting_document_paths ?? [],
            fn ($path): bool => is_string($path) && $path !== '',
        ));

        foreach ($this->validRemovalPaths($request, $paths) as $path) {
            $this->deleteFile($path);
            $paths = array_values(array_filter($paths, fn (string $existing): bool => $existing !== $path));
        }

        if ($request->hasFile('supporting_documents')) {
            $directory = $this->storageDirectory($bondRequest);

            /** @var array<int, UploadedFile> $files */
            $files = $request->file('supporting_documents');

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $paths[] = $file->store($directory, 'public');
            }
        }

        return array_values(array_slice($paths, 0, self::MAX_FILES));
    }

    /**
     * @param  list<string>  $paths
     */
    public function deleteAll(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $this->deleteFile($path);
            }
        }
    }

    public function deleteFile(string $path): void
    {
        if ($this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function validRemovalPaths(Request $request, array $paths): array
    {
        $requested = $request->input('removed_supporting_documents', []);

        if (! is_array($requested)) {
            return [];
        }

        return array_values(array_filter(
            $requested,
            fn ($path): bool => is_string($path) && in_array($path, $paths, true),
        ));
    }
}
