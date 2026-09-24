<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeleteIncompleteOrderService
{
    public function delete(Order $order): void
    {
        $order->refresh();

        if ($order->status !== 'DETAILS_INCOMPLETE') {
            throw new RuntimeException(
                'Only orders with incomplete details can be deleted.'
            );
        }

        if ($order->payments()->exists()
            || $order->designJobs()->exists()
            || $order->printJobs()->exists()
            || $order->packingJob()->exists()
            || $order->fulfilmentJob()->exists()) {
            throw new RuntimeException(
                'This order already has payment or production records and cannot be deleted.'
            );
        }

        DB::transaction(function () use ($order): void {
            $sideIds = $order->packageSides()->pluck('id');
            $eventIds = DB::table('order_events')
                ->whereIn('order_package_side_id', $sideIds)
                ->pluck('id');

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
