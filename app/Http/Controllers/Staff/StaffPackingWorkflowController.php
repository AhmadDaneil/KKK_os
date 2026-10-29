<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PackingJob;
use App\Models\PackingJobItem;
use App\Models\PackingJobProof;
use App\Models\User;
use App\Services\Fulfilment\CompleteCourierFulfilmentService;
use App\Services\Fulfilment\InitializeFulfilmentJobForOrderService;
use App\Services\Fulfilment\MarkPickupCollectedService;
use App\Services\Packing\MarkPackingJobPackedService;
use App\Services\Packing\StartPackingService;
use App\Services\Packing\VerifyPackingItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StaffPackingWorkflowController extends Controller
{
    public function showProof(
        Request $request,
        PackingJob $packingJob
    ): StreamedResponse {
        $user = $request->user();

        abort_unless(
            $user->isOperationManagement()
                || ($user->hasStaffRole(User::ROLE_OM)
                    && $packingJob->assigned_user_id === $user->id),
            404
        );

        $path = $packingJob->proof_storage_path;

        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            $packingJob->proof_original_name ?? 'packing-proof',
            ['Content-Type' => $packingJob->proof_mime_type ?? 'application/octet-stream']
        );
    }

    public function showProofFile(
        Request $request,
        PackingJob $packingJob,
        PackingJobProof $packingJobProof,
    ): StreamedResponse {
        $user = $request->user();

        abort_unless(
            $user->isOperationManagement()
                || ($user->hasStaffRole(User::ROLE_OM)
                    && $packingJob->assigned_user_id === $user->id),
            404,
        );

        abort_unless($packingJobProof->packing_job_id === $packingJob->id, 404);

        $path = $packingJobProof->storage_path;

        abort_unless(filled($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            $packingJobProof->original_name,
            [
                'Content-Type' => $packingJobProof->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function start(
        Request $request,
        PackingJob $packingJob,
        StartPackingService $service
    ): RedirectResponse {
        $this->authorizePackingOperation($request, $packingJob);

        try {
            $service->start(
                $packingJob,
                $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'packing_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Packing started successfully.'
        );
    }

    public function verifyItem(
        Request $request,
        PackingJob $packingJob,
        PackingJobItem $packingItem,
        VerifyPackingItemService $service
    ): RedirectResponse {
        $this->authorizePackingOperation($request, $packingJob);

        abort_unless(
            $packingItem->packing_job_id === $packingJob->id,
            404
        );

        try {
            $service->verify(
                $packingItem,
                $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'packing_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Packing item confirmed.'
        );
    }

    public function markPacked(
        Request $request,
        PackingJob $packingJob,
        MarkPackingJobPackedService $service,
        InitializeFulfilmentJobForOrderService $initializeFulfilment,
    ): RedirectResponse {
        $this->authorizePackingOperation($request, $packingJob);
        $packingJob->load('order.fulfilment');

        $isCourier = $packingJob->order->fulfilment?->method === 'COURIER';

        $validated = $request->validate([
            'courier_provider' => [$isCourier ? 'nullable' : 'exclude', 'required_with:tracking_number', 'string', 'max:255'],
            'tracking_number' => [$isCourier ? 'nullable' : 'exclude', 'required_with:courier_provider', 'string', 'max:255'],
            'packing_proofs' => [
                $isCourier ? 'nullable' : 'required_without:packing_proof',
                'array',
                'min:1',
                'max:10',
            ],
            'packing_proofs.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'packing_proof' => [
                $isCourier ? 'nullable' : 'required_without:packing_proofs',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ], [
            'packing_proofs.required_without' => 'Upload at least one photo of the packed items.',
            'packing_proofs.min' => 'Upload at least one photo of the packed items.',
            'packing_proofs.max' => 'You may upload up to 10 packing photos at a time.',
            'packing_proofs.*.image' => 'Packing proof files must be images.',
            'packing_proofs.*.max' => 'Each packing photo must not exceed 10 MB.',
            'packing_proof.required_without' => 'Upload at least one photo of the packed items.',
            'packing_proof.image' => 'Packing proof must be an image file.',
            'packing_proof.max' => 'Packing proof must not exceed 10 MB.',
        ]);

        $proofUploads = array_values($validated['packing_proofs'] ?? []);

        if (isset($validated['packing_proof'])) {
            $proofUploads[] = $validated['packing_proof'];
        }

        $storedProofs = [];

        try {
            foreach ($proofUploads as $proof) {
                $proofPath = $proof->store(
                    "packing-proofs/{$packingJob->id}",
                    'local',
                );

                if (! is_string($proofPath) || $proofPath === '') {
                    throw new RuntimeException('A packing photo could not be stored.');
                }

                $storedProofs[] = [
                    'file' => $proof,
                    'path' => $proofPath,
                ];
            }

            DB::transaction(function () use (
                $packingJob,
                $service,
                $initializeFulfilment,
                $request,
                $storedProofs,
                $validated,
                $isCourier
            ) {
                foreach ($storedProofs as $storedProof) {
                    $proof = $storedProof['file'];

                    $packingJob->proofs()->create([
                        'uploaded_by_user_id' => $request->user()->id,
                        'storage_path' => $storedProof['path'],
                        'original_name' => basename($proof->getClientOriginalName()),
                        'mime_type' => $proof->getMimeType(),
                    ]);
                }

                if ($storedProofs !== []) {
                    $primaryProof = $storedProofs[0];
                    $proof = $primaryProof['file'];

                    $packingJob->update([
                        'proof_storage_path' => $primaryProof['path'],
                        'proof_original_name' => basename($proof->getClientOriginalName()),
                        'proof_mime_type' => $proof->getMimeType(),
                    ]);
                }

                $packedJob = $service->markPacked(
                    $packingJob,
                    $request->user()
                );

                $fulfilmentJob = $initializeFulfilment->initialize(
                    $packedJob->order()->firstOrFail()
                );

                if ($isCourier && filled($validated['courier_provider'] ?? null)) {
                    $fulfilmentJob->update([
                        'courier_provider' => $validated['courier_provider'],
                        'tracking_number' => $validated['tracking_number'],
                    ]);
                }
            });
        } catch (RuntimeException $exception) {
            Storage::disk('local')->delete(array_column($storedProofs, 'path'));

            return back()->withErrors([
                'packing_job' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_column($storedProofs, 'path'));

            throw $exception;
        }

        return back()->with(
            'status',
            'Packing completed successfully.'
        );
    }

    public function completeCourier(
        Request $request,
        PackingJob $packingJob,
        CompleteCourierFulfilmentService $service,
    ): RedirectResponse {
        $this->authorizeAssignedPackingStaff($request, $packingJob);

        $packingJob->load(['order.fulfilment', 'order.fulfilmentJob']);

        $validated = $request->validate([
            'packing_proof' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'courier_provider' => [
                'required',
                'string',
                'max:255',
            ],
            'tracking_number' => [
                'required',
                'string',
                'max:255',
            ],
            'complete' => [
                'required',
                'accepted',
            ],
        ], [
            'packing_proof.required' => 'A photo of the parcel with its tracking label is required.',
            'packing_proof.image' => 'Courier proof must be an image file.',
            'packing_proof.max' => 'Courier proof must not exceed 10 MB.',
            'courier_provider.required' => 'Please enter the courier name.',
            'tracking_number.required' => 'Please enter the tracking number.',
            'complete.required' => 'Please mark COMPLETE before closing the order.',
            'complete.accepted' => 'Please mark COMPLETE before closing the order.',
        ]);

        $fulfilmentJob = $packingJob->order->fulfilmentJob;

        if (! $fulfilmentJob) {
            return back()->withErrors([
                'packing_job' => 'A fulfilment job is not yet available for this order.',
            ]);
        }

        if ($fulfilmentJob->method !== 'COURIER') {
            return back()->withErrors([
                'packing_job' => 'Courier completion is only allowed for COURIER orders.',
            ]);
        }

        if ($fulfilmentJob->status === 'COMPLETED') {
            return back()->with(
                'status',
                'Courier fulfilment already completed.'
            );
        }

        $proof = $validated['packing_proof'];

        $proofPath = $proof->store(
            "packing-proofs/{$packingJob->id}",
            'local'
        );

        try {
            DB::transaction(function () use (
                $packingJob,
                $fulfilmentJob,
                $service,
                $request,
                $validated,
                $proof,
                $proofPath
            ) {
                $packingJob->update([
                    'proof_storage_path' => $proofPath,
                    'proof_original_name' => basename(
                        $proof->getClientOriginalName()
                    ),
                    'proof_mime_type' => $proof->getMimeType(),
                ]);

                $service->complete(
                    $fulfilmentJob,
                    $validated['courier_provider'],
                    $validated['tracking_number'],
                    $request->user()
                );
            });
        } catch (RuntimeException $exception) {
            Storage::disk('local')->delete($proofPath);

            return back()->withErrors([
                'packing_job' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($proofPath);
            throw $exception;
        }

        return back()->with(
            'status',
            'Handover to the courier completed successfully.'
        );
    }

    public function collectPickup(
        Request $request,
        PackingJob $packingJob,
        MarkPickupCollectedService $service,
    ): RedirectResponse {
        $this->authorizeAssignedPackingStaff($request, $packingJob);

        $packingJob->load(['order.fulfilment', 'order.fulfilmentJob']);

        $validated = $request->validate([
            'completion_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'complete' => [
                'required',
                'accepted',
            ],
        ], [
            'complete.required' => 'Please mark COMPLETE before closing the order.',
            'complete.accepted' => 'Please mark COMPLETE before closing the order.',
        ]);

        $fulfilmentJob = $packingJob->order->fulfilmentJob;

        if (! $fulfilmentJob) {
            return back()->withErrors([
                'packing_job' => 'A fulfilment job is not yet available for this order.',
            ]);
        }

        if ($fulfilmentJob->method !== 'PICKUP') {
            return back()->withErrors([
                'packing_job' => 'Pickup completion is only allowed for PICKUP orders.',
            ]);
        }

        try {
            $service->collect(
                $fulfilmentJob,
                $validated['completion_reference'] ?? null,
                $request->user()
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'packing_job' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            'Customer pickup completed successfully.'
        );
    }

    private function authorizePackingOperation(
        Request $request,
        PackingJob $packingJob
    ): void {
        abort_unless(
            $request->user()->role === User::ROLE_OM
                || $packingJob->assigned_user_id === $request->user()->id,
            404
        );
    }

    private function authorizeAssignedPackingStaff(
        Request $request,
        PackingJob $packingJob
    ): void {
        abort_unless(
            $packingJob->assigned_user_id === $request->user()->id,
            404
        );
    }
}
