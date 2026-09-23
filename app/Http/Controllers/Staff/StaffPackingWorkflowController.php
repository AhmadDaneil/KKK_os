<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PackingJob;
use App\Models\PackingJobItem;
use App\Models\User;
use App\Services\Fulfilment\CompleteCourierFulfilmentService;
use App\Services\Fulfilment\InitializeFulfilmentJobForOrderService;
use App\Services\Packing\MarkPackingJobPackedService;
use App\Services\Packing\StartPackingService;
use App\Services\Packing\VerifyPackingItemService;
<<<<<<< HEAD
use App\Services\Fulfilment\InitializeFulfilmentJobForOrderService;
use App\Services\Fulfilment\CompleteCourierFulfilmentService;
use App\Services\Fulfilment\MarkPickupCollectedService;
=======
>>>>>>> main
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StaffPackingWorkflowController extends Controller
{
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
            'Packing item verified successfully.'
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
            'packing_proof' => [
                $isCourier ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ], [
            'packing_proof.required' => 'Bukti gambar barang yang telah dipack wajib dimuat naik.',
            'packing_proof.image' => 'Bukti packing mestilah fail gambar.',
            'packing_proof.max' => 'Saiz bukti packing tidak boleh melebihi 10 MB.',
        ]);

        $proof = $validated['packing_proof'] ?? null;
        $proofPath = null;

        if ($proof) {
            $proofPath = $proof->store(
                "packing-proofs/{$packingJob->id}",
                'local'
            );
        }

        try {
            DB::transaction(function () use (
                $packingJob,
                $service,
                $initializeFulfilment,
                $request,
                $proof,
                $proofPath,
                $validated,
                $isCourier
            ) {
                if ($proof && $proofPath) {
                    $packingJob->update([
                        'proof_storage_path' => $proofPath,
                        'proof_original_name' => basename(
                            $proof->getClientOriginalName()
                        ),
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
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            return back()->withErrors([
                'packing_job' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

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
            'packing_proof.required' => 'Bukti gambar parcel bersama label tracking wajib dimuat naik.',
            'packing_proof.image' => 'Bukti courier mestilah fail gambar.',
            'packing_proof.max' => 'Saiz bukti courier tidak boleh melebihi 10 MB.',
            'courier_provider.required' => 'Sila masukkan nama courier.',
            'tracking_number.required' => 'Sila masukkan tracking number.',
            'complete.required' => 'Sila tandakan COMPLETE sebelum menamatkan order.',
            'complete.accepted' => 'Sila tandakan COMPLETE sebelum menamatkan order.',
        ]);

        $fulfilmentJob = $packingJob->order->fulfilmentJob;

        if (! $fulfilmentJob) {
            return back()->withErrors([
                'packing_job' => 'Fulfilment job belum tersedia untuk order ini.',
            ]);
        }

        if ($fulfilmentJob->method !== 'COURIER') {
            return back()->withErrors([
                'packing_job' => 'Courier completion hanya dibenarkan untuk order COURIER.',
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
            'Courier fulfilment completed successfully.'
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
            'complete.required' =>
                'Sila tandakan COMPLETE sebelum menamatkan order.',
            'complete.accepted' =>
                'Sila tandakan COMPLETE sebelum menamatkan order.',
        ]);

        $fulfilmentJob = $packingJob->order->fulfilmentJob;

        if (! $fulfilmentJob) {
            return back()->withErrors([
                'packing_job' =>
                    'Fulfilment job belum tersedia untuk order ini.',
            ]);
        }

        if ($fulfilmentJob->method !== 'PICKUP') {
            return back()->withErrors([
                'packing_job' =>
                    'Pickup completion hanya dibenarkan untuk order PICKUP.',
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
            'Pickup fulfilment completed successfully.'
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
