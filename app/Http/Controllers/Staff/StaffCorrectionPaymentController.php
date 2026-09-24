<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Design\RequestArtworkCorrectionService;
use App\Services\Design\SyncOrderDesignStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffCorrectionPaymentController extends Controller
{
    public function approve(Request $request, PaymentTransaction $payment, RequestArtworkCorrectionService $correction, SyncOrderDesignStatusService $sync): RedirectResponse
    {
        abort_unless($request->user()->isOperationManagement() && $payment->payment_type === 'ARTWORK_CORRECTION', 403);
        DB::transaction(function () use ($request, $payment, $correction, $sync) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment->refresh();
            abort_unless($payment->status === 'PENDING' && ! $order->isTerminalOperationalStatus(), 422);
            $metadata = $payment->metadata;
            $job = $order->designJobs()->findOrFail($metadata['design_job_id']);
            abort_unless($job->status === 'DESIGN_READY'
                && $job->artworkVersions()->latest('version_number')->value('id') === $metadata['artwork_version_id'], 422);
            $correction->request($job, $metadata['correction_comment']);
            $sync->sync($order);
            $metadata['reviewed_by_user_id'] = $request->user()->id;
            $metadata['reviewed_at'] = now()->toIso8601String();
            $payment->update(['status' => 'PAID', 'paid_at' => now(), 'metadata' => $metadata]);
            $payment->events()->create(['event_type' => 'CORRECTION_PAYMENT_APPROVED', 'payload' => ['actor_user_id' => $request->user()->id], 'occurred_at' => now()]);
        });

        return back()->with('status', 'Bayaran RM10 disahkan. Designer boleh memulakan pembetulan.');
    }

    public function reject(Request $request, PaymentTransaction $payment): RedirectResponse
    {
        abort_unless($request->user()->isOperationManagement() && $payment->payment_type === 'ARTWORK_CORRECTION', 403);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $payment, $validated) {
            Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment->refresh();
            abort_unless($payment->status === 'PENDING', 422);
            $metadata = $payment->metadata;
            $metadata['rejection_reason'] = $validated['rejection_reason'];
            $metadata['reviewed_by_user_id'] = $request->user()->id;
            $metadata['reviewed_at'] = now()->toIso8601String();
            $payment->update(['status' => 'FAILED', 'metadata' => $metadata]);
            $payment->events()->create(['event_type' => 'CORRECTION_PAYMENT_REJECTED', 'payload' => ['actor_user_id' => $request->user()->id, 'reason' => $validated['rejection_reason']], 'occurred_at' => now()]);
        });

        return back()->with('status', 'Resit pembetulan ditolak. Customer boleh menghantar resit baharu.');
    }
}
