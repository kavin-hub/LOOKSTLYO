import crypto from 'node:crypto';
import { jsonResponse, supabaseRequest } from './_supabase.js';

const allowedStatuses = new Set(['Payment Verification Pending', 'Payment Verified', 'Dispatched', 'Out for Delivery', 'Delivered']);

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
    if (event.httpMethod !== 'POST') return { statusCode: 405, body: JSON.stringify({ success: false, message: 'Method not allowed' }) };
    if (!isValidAdminToken(event.headers.authorization?.replace(/^Bearer\s+/i, ''))) {
      return { statusCode: 401, body: JSON.stringify({ success: false, message: 'Unauthorized' }) };
    }

    const { orderId, status, note } = JSON.parse(event.body || '{}');
    if (!orderId || !allowedStatuses.has(status)) return { statusCode: 400, body: JSON.stringify({ success: false, message: 'Order ID and valid status are required.' }) };

    const orders = await supabaseRequest(`orders?order_id=eq.${encodeURIComponent(orderId)}&select=id,order_id,status`);
    const order = orders?.[0];
    if (!order) return jsonResponse(404, { success: false, message: 'Order not found.' });

    const updatedAt = new Date().toISOString();
    await supabaseRequest(`orders?id=eq.${order.id}`, { method: 'PATCH', headers: { Prefer: 'return=minimal' }, body: JSON.stringify({ status, updated_at: updatedAt }) });
    await supabaseRequest('order_status_history', { method: 'POST', headers: { Prefer: 'return=minimal' }, body: JSON.stringify({ order_id: order.id, status, note: note || `Order status updated to ${status}` }) });

    return jsonResponse(200, { success: true, orderId, status });
  } catch (error) {
    return { statusCode: 500, body: JSON.stringify({ success: false, message: error.message }) };
  }
};
