<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTrackingEvent;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CourierTrackingWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredToken = (string) config('services.courier.webhook_token', '');
        if ($configuredToken === '') {
            return response()->json(['message' => 'Courier webhook is not configured.'], 503);
        }

        $incomingToken = (string) $request->header('X-Courier-Token', '');
        if (! hash_equals($configuredToken, $incomingToken)) {
            return response()->json(['message' => 'Unauthorized webhook token.'], 401);
        }

        $data = $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'tracking_number' => ['required', 'string', 'max:120'],
            'courier_name' => ['nullable', 'string', 'max:50'],
            'provider' => ['nullable', 'string', 'max:80'],
            'event_status' => ['required', 'string', 'max:80'],
            'event_code' => ['nullable', 'string', 'max:80'],
            'event_time' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'payload' => ['nullable', 'array'],
        ]);

        $order = null;
        if (! empty($data['order_id'])) {
            $order = Order::query()->find((int) $data['order_id']);
        }

        if (! $order) {
            $order = Order::query()
                ->where('tracking_number', $data['tracking_number'])
                ->first();
        }

        if (! $order) {
            return response()->json([
                'ok' => true,
                'ignored' => true,
                'reason' => 'order_not_found',
            ], 202);
        }

        $occurredAt = isset($data['event_time'])
            ? Carbon::parse((string) $data['event_time'])
            : now();

        $event = OrderTrackingEvent::query()->create([
            'order_id' => $order->id,
            'tracking_number' => (string) ($data['tracking_number'] ?? $order->tracking_number),
            'courier_name' => (string) ($data['courier_name'] ?? $order->courier_name),
            'provider' => $data['provider'] ?? null,
            'event_status' => (string) $data['event_status'],
            'event_code' => $data['event_code'] ?? null,
            'location' => $data['location'] ?? null,
            'description' => $data['description'] ?? null,
            'occurred_at' => $occurredAt,
            'raw_payload' => $data['payload'] ?? $request->all(),
        ]);

        $normalizedStatus = Str::of((string) $data['event_status'])
            ->lower()
            ->replace('-', '_')
            ->replace(' ', '_')
            ->value();

        $didUpdateOrderStatus = false;

        if (in_array($normalizedStatus, ['shipped', 'in_transit', 'picked_up'], true)
            && $order->canTransitionTo(Order::STATUS_SHIPPED, 'system')) {
            $order->status = Order::STATUS_SHIPPED;
            $order->shipped_at = $order->shipped_at ?: $occurredAt;
            $didUpdateOrderStatus = true;
        }

        if (in_array($normalizedStatus, ['out_for_delivery', 'for_delivery'], true)
            && $order->canTransitionTo(Order::STATUS_OUT_FOR_DELIVERY, 'system')) {
            $order->status = Order::STATUS_OUT_FOR_DELIVERY;
            $didUpdateOrderStatus = true;
        }

        if (in_array($normalizedStatus, ['delivered', 'completed'], true)
            && $order->canTransitionTo(Order::STATUS_DELIVERED, 'system')) {
            $order->status = Order::STATUS_DELIVERED;
            $order->delivered_at = $order->delivered_at ?: $occurredAt;
            $didUpdateOrderStatus = true;
        }

        if ($didUpdateOrderStatus) {
            $order->save();
            $order->customer?->notify(new OrderStatusUpdated($order));
        }

        return response()->json([
            'ok' => true,
            'order_id' => $order->id,
            'event_id' => $event->id,
            'status_updated' => $didUpdateOrderStatus,
        ]);
    }
}
