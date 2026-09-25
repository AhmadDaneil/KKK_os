<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class DeleteIncompleteOrderService
{
    public function delete(Order $order): void
    {
        $order->refresh();

        DB::transaction(function () use ($order): void {
            $sideIds = $order->packageSides()->pluck('id');
            $designJobIds = DB::table('design_jobs')->where('order_id', $order->id)->pluck('id');
            $artworkVersionIds = DB::table('artwork_versions')->whereIn('design_job_id', $designJobIds)->pluck('id');
            $printJobIds = DB::table('print_jobs')->where('order_id', $order->id)->pluck('id');
            $packingJobIds = DB::table('packing_jobs')->where('order_id', $order->id)->pluck('id');
            $fulfilmentJobIds = DB::table('fulfilment_jobs')->where('order_id', $order->id)->pluck('id');
            $paymentIds = DB::table('payment_transactions')->where('order_id', $order->id)->pluck('id');
            $eventIds = DB::table('order_events')
                ->whereIn('order_package_side_id', $sideIds)
                ->pluck('id');

            DB::table('fulfilment_job_events')->whereIn('fulfilment_job_id', $fulfilmentJobIds)->delete();
            DB::table('fulfilment_jobs')->whereIn('id', $fulfilmentJobIds)->delete();
            DB::table('packing_job_events')->whereIn('packing_job_id', $packingJobIds)->delete();
            DB::table('packing_job_items')->whereIn('packing_job_id', $packingJobIds)->delete();
            DB::table('packing_jobs')->whereIn('id', $packingJobIds)->delete();
            DB::table('print_job_events')->whereIn('print_job_id', $printJobIds)->delete();
            DB::table('print_jobs')->whereIn('id', $printJobIds)->delete();
            DB::table('artwork_review_actions')->whereIn('design_job_id', $designJobIds)->delete();
            DB::table('design_job_events')->whereIn('design_job_id', $designJobIds)->delete();
            DB::table('artwork_versions')->whereIn('id', $artworkVersionIds)->delete();
            DB::table('design_jobs')->whereIn('id', $designJobIds)->delete();
            DB::table('payment_events')->whereIn('payment_transaction_id', $paymentIds)->delete();
            DB::table('payment_transactions')->whereIn('id', $paymentIds)->delete();
            DB::table('event_contacts')->whereIn('order_event_id', $eventIds)->delete();
            DB::table('order_events')->whereIn('order_package_side_id', $sideIds)->delete();
            DB::table('order_parents')->whereIn('order_package_side_id', $sideIds)->delete();
            DB::table('order_designs')->whereIn('order_package_side_id', $sideIds)->delete();
            DB::table('merge_jobs')->where('order_id', $order->id)->delete();
            DB::table('order_package_sides')->whereIn('id', $sideIds)->delete();
            DB::table('order_access_tokens')->where('order_fk', $order->id)->delete();
            DB::table('order_confirmations')->where('order_id', $order->id)->delete();
            DB::table('order_fulfilments')->where('order_id', $order->id)->delete();
            DB::table('order_couples')->where('order_id', $order->id)->delete();
            DB::table('order_status_events')->where('order_id', $order->id)->delete();

            $order->delete();
        });
    }
}
