<?php

namespace App\Http\Controllers;

use App\Models\PriceCatalogItem;
use App\Services\Orders\BuildCustomerProgressService;
use App\Services\Orders\CustomerOrderSessionAccessService;
use App\Services\Orders\ResolveOrderAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerDashboardController extends Controller
{
    public function show(
        Request $request,
        string $orderId,
        ResolveOrderAccessService $tokenAccess,
        CustomerOrderSessionAccessService $sessionAccess,
        BuildCustomerProgressService $customerProgress,
    ) {
        $plainToken = (string) $request->query('token', '');

        if ($plainToken !== '') {
            $order = $sessionAccess->establishFromToken($request, $orderId, $plainToken, $tokenAccess);

            return redirect()
                ->route('orders.dashboard', ['orderId' => $order->order_id])
                ->withHeaders([
                    'Cache-Control' => 'no-store',
                    'Referrer-Policy' => 'no-referrer',
                ]);
        }

        try {
            $order = $sessionAccess->resolve($request, $orderId);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 403) {
                throw $exception;
            }

            return redirect()->route('orders.access.create', [
                'order_id' => $orderId,
            ]);
        }

        $order->load([
            'couples',
            'packageSides.design',
            'packageSides.parents',
            'packageSides.event.contacts',
            'fulfilment',
            'fulfilmentJob',
            'packingJob',
            'payments',
            'invoice',
            'confirmation',
            'designJobs.artworkVersions',
            'progressUpdates' => fn ($query) => $query
                ->customerVisible()
                ->with('uploader:id,name')
                ->latest(),
        ]);

        $catalogItems = PriceCatalogItem::query()
            ->whereIn('item_type', ['PACKAGE_TIER', 'PACKAGE_COMBO'])
            ->where('is_active', true)
            ->get();

        $packageTiers = $catalogItems
            ->where('item_type', 'PACKAGE_TIER')
            ->filter(fn ($item) => data_get($item->metadata, 'package_type') === $order->package_type)
            ->values();

        $quantityOptions = $packageTiers
            ->map(fn ($item) => (int) data_get($item->metadata, 'card_quantity'))
            ->filter(fn ($quantity) => $quantity > 0)
            ->unique()
            ->sort()
            ->values();

        $validQuantityPairs = [];
        $orderSides = $order->packageSides->pluck('side')->all();

        if ((int) $order->package_count === 1) {
            $sides = $orderSides !== []
                ? $orderSides
                : ['LELAKI', 'PEREMPUAN'];

            foreach ($quantityOptions as $quantity) {
                foreach ($sides as $side) {
                    $validQuantityPairs[] = [$side => $quantity];
                }
            }
        } else {
            $comboItems = $catalogItems->where('item_type', 'PACKAGE_COMBO');

            foreach ($quantityOptions as $lelakiQuantity) {
                foreach ($quantityOptions as $perempuanQuantity) {
                    $combinedQuantity = $lelakiQuantity + $perempuanQuantity;

                    $hasCombinedTier = $quantityOptions->contains(
                        fn ($quantity) => $quantity === $combinedQuantity
                    );

                    $hasSpecialCombo = $comboItems->contains(function ($item) use (
                        $lelakiQuantity,
                        $perempuanQuantity,
                        $order
                    ): bool {
                        if (
                            data_get($item->metadata, 'package_type') !== $order->package_type
                            || (int) data_get($item->metadata, 'package_count') !== 2
                        ) {
                            return false;
                        }

                        $configured = collect(data_get($item->metadata, 'side_quantities', []))
                            ->map(fn ($quantity) => (int) $quantity)
                            ->sort()
                            ->values()
                            ->all();

                        $selected = collect([$lelakiQuantity, $perempuanQuantity])
                            ->sort()
                            ->values()
                            ->all();

                        return $configured === $selected;
                    });

                    if ($hasCombinedTier || $hasSpecialCombo) {
                        $validQuantityPairs[] = [
                            'LELAKI' => $lelakiQuantity,
                            'PEREMPUAN' => $perempuanQuantity,
                        ];
                    }
                }
            }
        }

        return view('orders.dashboard', [
            'order' => $order,
            'progress' => $customerProgress->build($order),
            'quantityOptions' => $quantityOptions,
            'validQuantityPairs' => $validQuantityPairs,
        ]);
    }
}
