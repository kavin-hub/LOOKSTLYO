// netlify/functions/create-order.js
// Creates a customer, order, line items, and initial status in Supabase.
import { jsonResponse, supabaseRequest } from './_supabase.js';
import { hashPassword } from './_auth.js';

export const handler = async (event) => {
  try {
    // Only accept POST requests
    if (event.httpMethod !== 'POST') {
      return {
        statusCode: 405,
        body: JSON.stringify({ success: false, message: 'Method not allowed' })
      };
    }

    const data = JSON.parse(event.body);

    // Validate required fields
    const required = ['fullName', 'mobile', 'email', 'password', 'address', 'city', 'state', 'pincode', 'cartItems', 'totalAmount'];
    for (const field of required) {
      if (!data[field]) {
        return {
          statusCode: 400,
          body: JSON.stringify({ success: false, message: `Missing required field: ${field}` })
        };
      }
    }

    // Generate Order ID (format: LS{YEAR}{COUNT})
    const year = new Date().getFullYear().toString().slice(-2);
    const orderId = `LS${year}${Date.now().toString().slice(-6)}`;

    // Create order object
    const existingCustomers = await supabaseRequest(`customers?mobile=eq.${encodeURIComponent(data.mobile)}&select=id`);
    let customerId = existingCustomers?.[0]?.id;
    if (customerId) {
      const updates = { full_name: data.fullName, email: data.email, address: data.address, city: data.city, state: data.state, pincode: data.pincode, updated_at: new Date().toISOString() };
      if (data.password) updates.password_hash = hashPassword(data.password);
      await supabaseRequest(`customers?id=eq.${customerId}`, {
        method: 'PATCH',
        headers: { Prefer: 'return=minimal' },
        body: JSON.stringify(updates)
      });
    } else {
      const createdCustomers = await supabaseRequest('customers', {
        method: 'POST',
        headers: { Prefer: 'return=representation' },
        body: JSON.stringify({ full_name: data.fullName, mobile: data.mobile, email: data.email, address: data.address, city: data.city, state: data.state, pincode: data.pincode, password_hash: data.password ? hashPassword(data.password) : null })
      });
      customerId = createdCustomers?.[0]?.id;
    }

    const createdOrders = await supabaseRequest('orders', {
      method: 'POST',
      headers: { Prefer: 'return=representation' },
      body: JSON.stringify({ order_id: orderId, customer_id: customerId, total_amount: data.totalAmount, address: data.address, city: data.city, state: data.state, pincode: data.pincode, order_notes: data.orderNotes || '', utr_number: data.utrNumber || '', payment_screenshot: data.paymentScreenshot || null })
    });
    const storedOrderId = createdOrders?.[0]?.id;
    const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
    await supabaseRequest('order_items', {
      method: 'POST',
      headers: { Prefer: 'return=minimal' },
      body: JSON.stringify((data.cartItems || []).map(item => {
        const candidateProductId = item.productId || item.id;
        return { order_id: storedOrderId, product_id: uuidPattern.test(String(candidateProductId || '')) ? candidateProductId : null, product_name: item.productName || item.name, size: item.size || null, color: item.color || null, quantity: item.quantity, unit_price: item.price };
      }))
    });
    await supabaseRequest('order_status_history', {
      method: 'POST',
      headers: { Prefer: 'return=minimal' },
      body: JSON.stringify({ order_id: storedOrderId, status: 'Payment Verification Pending', note: 'Order received and waiting for payment verification' })
    });

    return jsonResponse(200, { success: true, message: 'Order created successfully', orderId });
  } catch (error) {
    return {
      statusCode: 500,
      body: JSON.stringify({
        success: false,
        message: 'Error creating order',
        error: error.message
      })
    };
  }
};