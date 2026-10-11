// netlify/functions/login.js
// Unified sign-in: detects whether the person is the admin or a customer
// and returns the matching role + token so the frontend knows where to send them.
import { jsonResponse, supabaseRequest } from './_supabase.js';
import { createAdminToken, createCustomerToken, verifyPassword } from './_auth.js';

export const handler = async (event) => {
  try {
    if (event.httpMethod !== 'POST') {
      return jsonResponse(405, { success: false, message: 'Method not allowed' });
    }

    const { identifier, password } = JSON.parse(event.body || '{}');
    const login = String(identifier || '').trim();
    if (!login || !password) {
      return jsonResponse(400, { success: false, message: 'Enter your mobile/email (or admin username) and password.' });
    }

    // 1) Admin check first (username + password from environment).
    const adminUser = process.env.ADMIN_USERNAME || 'admin';
    const adminPass = process.env.ADMIN_PASSWORD || 'admin123';
    if (login === adminUser && password === adminPass) {
      return jsonResponse(200, {
        success: true,
        role: 'admin',
        name: 'Administrator',
        token: createAdminToken(adminUser),
        redirect: '/pages/billing.html'
      });
    }

    // 2) Otherwise treat the identifier as a customer mobile/email.
    const enc = encodeURIComponent(login);
    let rows;
    try {
      rows = await supabaseRequest(
        `customers?or=(mobile.eq.${enc},email.eq.${enc})&select=id,full_name,email,mobile,password_hash&limit=1`
      );
    } catch (error) {
      return jsonResponse(200, {
        success: false,
        message: 'Customer sign-in is unavailable right now. Please try again later.'
      });
    }

    const customer = Array.isArray(rows) ? rows[0] : null;
    if (!customer || !customer.password_hash || !verifyPassword(password, customer.password_hash)) {
      return jsonResponse(200, { success: false, message: 'Invalid credentials.' });
    }

    return jsonResponse(200, {
      success: true,
      role: 'customer',
      name: customer.full_name || '',
      token: createCustomerToken(customer.id),
      redirect: '/index.html'
    });
  } catch (error) {
    return jsonResponse(500, {
      success: false,
      message: 'Login error',
      error: error.message
    });
  }
};
