# Order Status Flow - E-Commerce Standard (Shopee/TikTok/eBay Style)

## Correct Order Lifecycle

```
CUSTOMER PLACES ORDER
    ↓
[awaiting_payment] → Customer pays
    ↓
[paid] → Seller accepts
    ↓
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SELLER RESPONSIBILITY (Seller can manage these statuses)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    ↓
[to_pack] → Seller prepares items
    ↓
[ready_to_ship] → Seller marks ready
    ↓
[shipped] → Seller hands to courier (SELLER RESPONSIBILITY ENDS HERE)
    ↓
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
COURIER/SYSTEM RESPONSIBILITY (Only admin/system can update)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    ↓
[out_for_delivery] → Courier out for delivery (auto-update or admin)
    ↓
[delivered] → Parcel delivered (auto-update or admin)
    ↓
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CUSTOMER CONFIRMATION (Customer action required)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    ↓
[received] → Customer confirms receipt (customer clicks "Order Received")
    ↓
[completed] → Auto-complete after X days OR admin completes
```

## Who Can Update What Status

### Seller Actions (ONLY these statuses)
- ✅ `paid` → `to_pack` (Accept order)
- ✅ `to_pack` → `ready_to_ship` (Mark as packed)
- ✅ `ready_to_ship` → `shipped` (Hand to courier)
- ✅ Can cancel before shipped
- ❌ **CANNOT** set to `out_for_delivery`
- ❌ **CANNOT** set to `delivered`
- ❌ **CANNOT** set to `received`
- ❌ **CANNOT** set to `completed`

### Customer Actions
- ✅ `delivered` → `received` (Confirm receipt)
- ✅ Can cancel within 30 minutes of placing order
- ✅ Can report issues/disputes

### System/Admin Actions
- ✅ `shipped` → `out_for_delivery` (Courier tracking update)
- ✅ `out_for_delivery` → `delivered` (Courier tracking update)
- ✅ `delivered` → `completed` (Auto-complete after X days)
- ✅ `received` → `completed` (Auto-complete)
- ✅ Can manage any status transition

## Status Transition Rules (Updated)

```php
'shipped' => [
    'out_for_delivery' => ['admin', 'system'], // REMOVED 'seller'
    'delivered' => ['admin', 'system'], // REMOVED 'seller'
],

'out_for_delivery' => [
    'delivered' => ['admin', 'system'], // REMOVED 'seller'
],

'delivered' => [
    'received' => ['customer', 'admin', 'system'], // Customer confirms
    'completed' => ['admin', 'system'], // Auto-complete
],

'received' => [
    'completed' => ['admin', 'system'], // Auto-complete
],
```

## Why This Flow?

### Real E-Commerce Platforms (Shopee, TikTok Shop, eBay, Lazada)

1. **Seller's job ends at "Shipped"**
   - Once seller hands package to courier, they can't control delivery
   - Prevents sellers from falsely marking orders as delivered
   - Protects both buyer and seller

2. **Courier/System updates delivery status**
   - `out_for_delivery` and `delivered` are based on courier tracking
   - Automated via courier API webhooks
   - Admin can manually update if needed

3. **Customer confirms receipt**
   - Customer clicks "Order Received" button
   - This triggers `received` status
   - Protects customer from auto-completion before they actually receive item

4. **System auto-completes**
   - After customer confirms receipt, order auto-completes
   - OR after X days from delivery (if customer doesn't confirm)
   - Money released to seller only after completion

## Files Modified

1. **`app/Models/Order.php`** - Updated `canTransitionTo()` method
   - Removed seller from `shipped → out_for_delivery`
   - Removed seller from `shipped → delivered`
   - Removed seller from `out_for_delivery → delivered`

2. **`resources/views/components/seller/⚡order-manager.blade.php`**
   - Removed `out_for_delivery` from seller's allowed status updates
   - Updated comments to clarify seller responsibility ends at shipped

3. **`resources/views/components/customer/⚡orders.blade.php`**
   - Fixed `markReceived()` to set status to `RECEIVED` (not `DELIVERED`)
   - Customer now properly confirms receipt

## Next Steps

### For Complete E-Commerce Flow:

1. **Add Courier Webhook Integration**
   - Integrate with J&T, LBC, Flash Express APIs
   - Auto-update `out_for_delivery` and `delivered` based on courier tracking

2. **Add Auto-Completion Logic**
   - Create scheduled job to auto-complete orders X days after delivery
   - Typical: 7-15 days after delivery if customer doesn't confirm

3. **Add "Order Received" Button for Customers**
   - Show button when order status is `delivered`
   - Calls `markReceived()` method
   - Changes status to `received`

4. **Update UI to Show Correct Actions**
   - Sellers see: Accept, Pack, Ship (no more buttons after shipped)
   - Customers see: "Order Received" button when delivered
   - Clear status indicators for each stage

## Testing the Flow

1. **Seller Flow:**
   ```
   Order placed → Seller clicks "Accept" → to_pack
   → Seller clicks "Mark Packed" → ready_to_ship
   → Seller clicks "Mark Shipped" → shipped
   → SELLER CANNOT DO ANYTHING MORE
   ```

2. **System/Admin Flow:**
   ```
   Order shipped → Admin/System updates → out_for_delivery
   → Admin/System updates → delivered
   ```

3. **Customer Flow:**
   ```
   Order delivered → Customer clicks "Order Received" → received
   → System auto-completes → completed
   ```

This matches the standard e-commerce flow used by major platforms worldwide.
