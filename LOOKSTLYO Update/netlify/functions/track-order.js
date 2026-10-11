// netlify/functions/track-order.js
// Tracks order status by Order ID and Mobile Number.
import { jsonResponse, supabaseRequest } from './_supabase.js';

export const handler = async (event) => {
  try {
    const { orderId, mobile } = event.queryStringParameters || {};

    if (!orderId || !mobile) {
      return {
        statusCode: 400,
        body: JSON.stringify({
          success: false,
          message: 'Order ID and mobile number required'
        })
      };
    }

    const rows = await supabaseRequest(`orders?order_id=eq.${encodeURIComponent(orderId)}&select=*,customers!inner(mobile),order_items(*),order_status_history(*)&customers.mobile=eq.${encodeURIComponent(mobile)}`);
    const storedOrder = rows?.[0];
    if (!storedOrder) return jsonResponse(404, { success: false, message: 'Order not found' });

    return jsonResponse(200, {
      success: true,
      order: {
        orderId: storedOrder.order_id,
        mobile: storedOrder.customers.mobile,
        status: storedOrder.status,
        totalAmount: storedOrder.total_amount,
        createdAt: storedOrder.created_at,
        items: (storedOrder.order_items || []).map(item => ({ productName: item.product_name, quantity: item.quantity, size: item.size, color: item.color, price: item.unit_price })),
        timeline: (storedOrder.order_status_history || []).map(entry => ({ status: entry.status, date: entry.created_at, note: entry.note || '' }))
      }
    });
  } catch (error) {
    return {
      statusCode: 500,
      body: JSON.stringify({
        success: false,
        message: 'Error tracking order',
        error: error.message
      })
    };
  }
};
