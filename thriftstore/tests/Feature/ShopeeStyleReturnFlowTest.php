<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderDispute;

class ShopeeStyleReturnFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the full Shopee-style return flow for seller and buyer.
     */
    public function test_shopee_style_return_flow()
    {
        // Create buyer and seller
        $buyer = User::factory()->create(['role' => 'customer']);
        $seller = User::factory()->create(['role' => 'seller']);

        // Create an order
        $order = Order::factory()->create([
            'customer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'delivered',
        ]);

        // Buyer submits a dispute (return request)
        $this->actingAs($buyer);
        $response = $this->post(route('customer.orders.submitIssue', $order), [
            'issueReason' => 'not_as_described',
            'issueBody' => 'Item not as described',
        ]);
        $response->assertStatus(302);
        $dispute = OrderDispute::where('order_id', $order->id)->first();
        $this->assertNotNull($dispute);
        $this->assertEquals(OrderDispute::STATUS_OPEN, $dispute->status);

        // Seller responds to dispute
        $this->actingAs($seller);
        $response = $this->post(route('seller.orders.submitDisputeResponse', $dispute), [
            'response_note' => 'Please return the item',
        ]);
        $response->assertStatus(302);
        $dispute->refresh();
        $this->assertEquals(OrderDispute::STATUS_SELLER_REVIEW, $dispute->status);

        // Seller requests return
        $response = $this->post(route('seller.orders.requestReturn', $dispute));
        $response->assertStatus(302);
        $dispute->refresh();
        $this->assertEquals(OrderDispute::STATUS_RETURN_REQUESTED, $dispute->status);

        // Buyer submits return tracking
        $this->actingAs($buyer);
        $response = $this->post(route('customer.orders.submitReturnTracking', $dispute), [
            'returnTrackingNumber' => 'JNT-123456789',
        ]);
        $response->assertStatus(302);
        $dispute->refresh();
        $this->assertEquals(OrderDispute::STATUS_RETURN_IN_TRANSIT, $dispute->status);

        // Seller marks return as received
        $this->actingAs($seller);
        $response = $this->post(route('seller.orders.confirmReturnReceived', $dispute));
        $response->assertStatus(302);
        $dispute->refresh();
        $this->assertEquals(OrderDispute::STATUS_RETURN_RECEIVED, $dispute->status);

        // Seller marks refund as complete
        $response = $this->post(route('seller.orders.markRefundComplete', $dispute));
        $response->assertStatus(302);
        $dispute->refresh();
        $this->assertEquals(OrderDispute::STATUS_REFUND_COMPLETED, $dispute->status);
    }
}
