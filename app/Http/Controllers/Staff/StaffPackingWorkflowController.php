<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PackingJob;
use App\Models\PackingJobItem;
use App\Services\Packing\MarkPackingJobPackedService;
use App\Services\Packing\StartPackingService;
use App\Services\Packing\VerifyPackingItemService;
use App\Services\Fulfilment\InitializeFulfilmentJobForOrderService;
use App\Services\Fulfilment\MarkCourierShippedService;
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
        $this->authorizeAssignedPackingStaff($request, $packingJob);

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
        $this->authorizeAssignedPackingStaff($request, $packingJob);

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
        MarkCourierShippedService $markShipped,
    ): RedirectResponse {
        $this->authorizeAssignedPackingStaff($request, $packingJob);
        $packingJob->load('order.fulfilment');

        $isCourier = $packingJob->order->fulfilment?->method === 'COURIER';
        $validated = $request->validate([
            'packing_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'courier_provider' => [$isCourier ? 'required' : 'nullable', 'string', 'max:255'],
            'tracking_number' => [$isCourier ? 'required' : 'nullable', 'string', 'max:255'],
        ], [
            'packing_proof.required' => 'Bukti gambar barang yang telah dipack wajib dimuat naik.',
            'packing_proof.image' => 'Bukti packing mestilah fail gambar.',
            'packing_proof.max' => 'Saiz bukti packing tidak boleh melebihi 10 MB.',
            'courier_provider.required' => 'Sila masukkan nama courier.',
            'tracking_number.required' => 'Sila masukkan tracking number untuk penghantaran courier.',
        ]);

        $proof = $validated['packing_proof'];
        $proofPath = $proof->store("packing-proofs/{$packingJob->id}", 'local');

        try {
            DB::transaction(function () use ($packingJob, $service, $initializeFulfilment, $markShipped, $request, $validated, $proof, $proofPath, $isCourier) {
                $packingJob->update([
                    'proof_storage_path' => $proofPath,
                    'proof_original_name' => $proof->getClientOriginalName(),
                    'proof_mime_type' => $proof->getMimeType(),
                ]);

                $packedJob = $service->markPacked($packingJob, $request->user());
                $fulfilmentJob = $initializeFulfilment->initialize($packedJob->order()->firstOrFail());

                if ($isCourier) {
                    $markShipped->ship(
                        $fulfilmentJob,
                        $validated['courier_provider'],
                        $validated['tracking_number'],
                        $request->user(),
                    );
                }
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
            'Packing completed successfully.'
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
