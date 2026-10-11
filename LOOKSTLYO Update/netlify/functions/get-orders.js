import crypto from 'node:crypto';
import { jsonResponse, supabaseRequest } from './_supabase.js';

function isValidAdminToken(token) {
  try {
    const decoded = Buffer.from(token || '', 'base64url').toString('utf8');
    const parts = decoded.split(':');
    if (parts.length !== 3) return false;
    const [username, timestamp, signature] = parts;
    const age = Date.now() - Number(timestamp);
    if (!username || !Number.isFinite(Number(timestamp)) || age < 0 || age > 24 * 60 * 60 * 1000) return false;
    const secret = process.env.ADMIN_TOKEN_SECRET || 'change-this-admin-token-secret';
    const expected = crypto.createHmac('sha256', secret).update(`${username}:${timestamp}`).digest('hex');
    return crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected));
  } catch (error) {
    return false;
  }
}

export const handler = async (event) => {
  try {
    if (event.httpMethod !== 'GET') return { statusCode: 405, body: JSON.stringify({ success: false, message: 'Method not allowed' }) };
    if (!isValidAdminToken(event.headers.authorization?.replace(/^Bearer\s+/i, ''))) {
      return { statusCode: 401, body: JSON.stringify({ success: false, message: 'Unauthorized' }) };
    }

    const storedOrders = await supabaseRequest('orders?select=*,customers(full_name,mobile,email),order_items(*),order_status_history(*)&order=created_at.desc');
    const orders = (storedOrders || []).map(order => ({
      orderId: order.order_id,
      customerName: order.customers?.full_name || '',
      mobile: order.customers?.mobile || '',
      email: order.customers?.email || '',
      totalAmount: order.total_amount,
      status: order.status,
      createdAt: order.created_at,
      items: order.order_items || [],
      timeline: order.order_status_history || []
    }));
    return jsonResponse(200, { success: true, orders });
  } catch (error) {
    return { statusCode: 500, body: JSON.stringify({ success: false, message: error.message }) };
  }
};
