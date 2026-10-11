// netlify/functions/_auth.js
// Shared password hashing + token helpers for authentication functions.
import crypto from 'node:crypto';

function adminSecret() {
  return process.env.ADMIN_TOKEN_SECRET || 'change-this-admin-token-secret';
}

// Passwords are stored as: scrypt$<saltHex>$<hashHex>
export function hashPassword(password) {
  const salt = crypto.randomBytes(16).toString('hex');
  const derived = crypto.scryptSync(String(password), salt, 64).toString('hex');
  return `scrypt$${salt}$${derived}`;
}

export function verifyPassword(password, stored) {
  try {
    const parts = String(stored || '').split('$');
    if (parts.length !== 3 || parts[0] !== 'scrypt') return false;
    const derived = crypto.scryptSync(String(password), parts[1], 64);
    const expected = Buffer.from(parts[2], 'hex');
    if (derived.length !== expected.length) return false;
    return crypto.timingSafeEqual(derived, expected);
  } catch (error) {
    return false;
  }
}

function sign(payload) {
  const signature = crypto.createHmac('sha256', adminSecret()).update(payload).digest('hex');
  return Buffer.from(`${payload}:${signature}`).toString('base64url');
}

// Matches the format validated by get-orders.js / update-order.js:
// base64url("<username>:<timestamp>:<signature>")
export function createAdminToken(username) {
  return sign(`${username}:${Date.now()}`);
}

// Customer tokens use a distinct 4-part payload so they are never accepted
// as admin tokens by get-orders.js / update-order.js.
export function createCustomerToken(customerId) {
  return sign(`customer:${customerId}:${Date.now()}`);
}
