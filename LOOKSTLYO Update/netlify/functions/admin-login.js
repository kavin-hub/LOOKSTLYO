// netlify/functions/admin-login.js
// Admin authentication with Firebase
import crypto from 'node:crypto';

function createAdminToken(username) {
  const payload = `${username}:${Date.now()}`;
  const secret = process.env.ADMIN_TOKEN_SECRET || 'change-this-admin-token-secret';
  const signature = crypto.createHmac('sha256', secret).update(payload).digest('hex');
  return Buffer.from(`${payload}:${signature}`).toString('base64url');
}

export const handler = async (event) => {
  try {
    if (event.httpMethod !== 'POST') {
      return {
        statusCode: 405,
        body: JSON.stringify({ success: false, message: 'Method not allowed' })
      };
    }

    const { username, password } = JSON.parse(event.body);

    // In production, verify against Firebase
    // Default credentials for testing
    if (username === 'admin' && password === 'admin123') {
      const token = createAdminToken(username);

      return {
        statusCode: 200,
        headers: {
          "Content-Type": "application/json",
          "Access-Control-Allow-Origin": "*",
          "Set-Cookie": `adminToken=${token}; Path=/; HttpOnly; SameSite=Strict`
        },
        body: JSON.stringify({
          success: true,
          message: 'Login successful',
          token: token
        })
      };
    }

    return {
      statusCode: 401,
      body: JSON.stringify({
        success: false,
        message: 'Invalid credentials'
      })
    };
  } catch (error) {
    return {
      statusCode: 500,
      body: JSON.stringify({
        success: false,
        message: 'Login error',
        error: error.message
      })
    };
  }
};
