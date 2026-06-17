<?php

use App\Models\BondRequest;
use App\Models\Deposit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Deposit::query()
            ->whereNotNull('receipt_path')
            ->orderBy('id')
            ->each(function (Deposit $deposit): void {
                $this->moveFromPublicToLocal($deposit->receipt_path);
            });

        BondRequest::query()
            ->whereNotNull('supporting_document_paths')
            ->orderBy('id')
            ->each(function (BondRequest $bondRequest): void {
                $paths = $bondRequest->supporting_document_paths ?? [];

                if (! is_array($paths)) {
                    return;
                }

                foreach ($paths as $path) {
                    if (is_string($path) && $path !== '') {
                        $this->moveFromPublicToLocal($path);
                    }
                }
            });
    }

    public function down(): void
    {
        Deposit::query()
            ->whereNotNull('receipt_path')
            ->orderBy('id')
            ->each(function (Deposit $deposit): void {
                $this->moveFromLocalToPublic($deposit->receipt_path);
            });

        BondRequest::query()
            ->whereNotNull('supporting_document_paths')
            ->orderBy('id')
            ->each(function (BondRequest $bondRequest): void {
                $paths = $bondRequest->supporting_document_paths ?? [];

                if (! is_array($paths)) {
                    return;
                }

                foreach ($paths as $path) {
                    if (is_string($path) && $path !== '') {
                        $this->moveFromLocalToPublic($path);
                    }
                }
            });
    }

    private function moveFromPublicToLocal(string $relativePath): void
    {
        if (! Storage::disk('public')->exists($relativePath)) {
            return;
        }

        if (Storage::disk('local')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);

            return;
        }

        $directory = dirname($relativePath);

        if ($directory !== '.' && $directory !== '') {
            Storage::disk('local')->makeDirectory($directory);
        }

        Storage::disk('local')->writeStream(
            $relativePath,
            Storage::disk('public')->readStream($relativePath),
        );

        Storage::disk('public')->delete($relativePath);
    }

    private function moveFromLocalToPublic(string $relativePath): void
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            return;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('local')->delete($relativePath);

            return;
        }

        $directory = dirname($relativePath);

        if ($directory !== '.' && $directory !== '') {
            Storage::disk('public')->makeDirectory($directory);
        }

        Storage::disk('public')->writeStream(
            $relativePath,
            Storage::disk('local')->readStream($relativePath),
        );

        Storage::disk('local')->delete($relativePath);
    }
};
