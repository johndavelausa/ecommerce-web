# Simplified Non-Receipt Flow (No Dispute System)

## User's Request
Remove the dispute system for "Item Didn't Receive". Instead:
- Customer clicks "Item Didn't Receive"
- Seller sees the report
- Seller provides explanation/reason (e.g., "Parcel hijacked by courier", "Parcel lost", "Delivered to neighbor", etc.)
- Direct communication, no formal dispute process

## New Flow

### **Step 1: Customer Reports Non-Receipt**
- Customer clicks "Item Didn't Receive" button
- System records the report
- Seller gets notified

### **Step 2: Seller Responds**
Seller provides explanation by selecting from common reasons:
- Parcel hijacked/stolen by courier
- Parcel lost in transit
- Delivered to neighbor/security
- Wrong address provided
- Customer wasn't home (multiple attempts)
- Parcel returned to sender
- Other (custom explanation)

### **Step 3: Resolution**
Based on seller's explanation:
- **Seller accepts responsibility** → Issues refund
- **Courier issue** → Seller files claim with courier, may refund customer
- **Customer issue** → Seller explains, may or may not refund

## Implementation Options

### **Option A: Use Existing Dispute Table (Simplified)**
Keep `order_disputes` table but simplify:
- Remove all status transitions
- Just two fields matter:
  - `reason_code` = 'parcel_not_received' (customer report)
  - `seller_response_note` = seller's explanation
  - `seller_resolution_action` = 'refund' or 'no_refund'

### **Option B: New Simple Table**
Create `order_non_receipt_reports`:
- `order_id`
- `customer_id`
- `seller_id`
- `reported_at`
- `seller_explanation`
- `seller_action` (refund/no_refund)
- `resolved_at`

### **Option C: Just Add Fields to Orders Table**
Add to `orders`:
- `non_receipt_reported_at`
- `seller_non_receipt_explanation`
- `seller_non_receipt_action`

## Recommended: Option A (Simplify Existing)

Keep the dispute table but make it super simple:
- Customer reports → Creates dispute with status `open`
- Seller responds → Updates `seller_response_note` and `seller_resolution_action`
- Status changes to `closed`
- No complex state machine, just open → closed

## UI Changes

### **Customer Side:**
- "Item Didn't Receive" button → Creates report
- Shows: "Waiting for seller response..."
- Once seller responds: Shows seller's explanation

### **Seller Side:**
- Notification: "Customer reported non-receipt for Order #123"
- Form with:
  - **Reason dropdown** (common reasons)
  - **Explanation textarea** (details)
  - **Action buttons:**
    - "Accept & Refund Customer"
    - "Explain (No Refund)"

## Benefits
✅ Much simpler than full dispute system
✅ Direct seller-customer communication
✅ Seller provides context/explanation
✅ Faster resolution
✅ No complex status transitions
