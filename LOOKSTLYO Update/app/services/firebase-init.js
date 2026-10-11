// firebase-init.js
// Initialize Firebase and set up Realtime Database connection

import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.0/firebase-app.js";
import { getDatabase, ref, get, set, push, update, remove, query, orderByChild, equalTo } from "https://www.gstatic.com/firebasejs/10.7.0/firebase-database.js";
import { getAuth, signInAnonymously } from "https://www.gstatic.com/firebasejs/10.7.0/firebase-auth.js";

// Your Firebase config (from Firebase Console)
const firebaseConfig = {
  apiKey: localStorage.getItem('firebaseApiKey') || "YOUR_API_KEY",
  authDomain: localStorage.getItem('firebaseAuthDomain') || "YOUR_PROJECT.firebaseapp.com",
  projectId: localStorage.getItem('firebaseProjectId') || "YOUR_PROJECT_ID",
  storageBucket: localStorage.getItem('firebaseStorageBucket') || "YOUR_PROJECT.appspot.com",
  messagingSenderId: localStorage.getItem('firebaseMessagingSenderId') || "YOUR_MESSAGING_SENDER_ID",
  appId: localStorage.getItem('firebaseAppId') || "YOUR_APP_ID"
};

let app, database, auth;

export async function initFirebase() {
  try {
    app = initializeApp(firebaseConfig);
    database = getDatabase(app);
    auth = getAuth(app);
    
    // Sign in anonymously for public access
    await signInAnonymously(auth);
    console.log('Firebase initialized successfully');
    return { app, database, auth };
  } catch (error) {
    console.error('Firebase initialization error:', error);
    throw error;
  }
}

// Get all products
export async function getProducts() {
  try {
    const response = await fetch('/.netlify/functions/get-products');
    const data = await readJsonResponse(response, 'Product service');
    return data.products || [];
  } catch (error) {
    console.error('Error fetching products:', error);
    return [];
  }
}

// Create order via Netlify Function
export async function createOrder(orderData) {
  try {
    if (window.location.protocol === 'file:') {
      const orderId = `LSLOCAL${Date.now()}`;
      const createdAt = new Date().toISOString();
      const order = {
        orderId,
        customerName: orderData.fullName,
        mobile: orderData.mobile,
        email: orderData.email,
        totalAmount: orderData.totalAmount,
        status: 'Payment Verification Pending',
        createdAt,
        items: (orderData.cartItems || []).map(item => ({ ...item, productName: item.productName || item.name })),
        timeline: [{ status: 'Payment Verification Pending', date: createdAt, note: 'Order received and waiting for payment verification' }]
      };
      const orders = JSON.parse(localStorage.getItem('localOrders') || '[]');
      orders.unshift(order);
      localStorage.setItem('localOrders', JSON.stringify(orders));
      return { success: true, orderId };
    }

    const response = await fetch('/.netlify/functions/create-order', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(orderData)
    });
    
    const responseText = await response.text();
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (parseError) {
      const contentType = response.headers.get('content-type') || 'unknown content type';
      throw new Error(`Order service returned ${response.status} (${contentType}). Redeploy Netlify Functions and verify netlify.toml.`);
    }
    if (!response.ok) {
      return { success: false, message: data.message || `Order service returned HTTP ${response.status}` };
    }
    if (data.success) {
      return { success: true, orderId: data.orderId };
    }
    return { success: false, message: data.message };
  } catch (error) {
    console.error('Error creating order:', error);
    return { success: false, message: error.message };
  }
}

// Track order
export async function trackOrder(orderId, mobile) {
  try {
    if (window.location.protocol === 'file:') {
      const order = JSON.parse(localStorage.getItem('localOrders') || '[]')
        .find(item => item.orderId === orderId && String(item.mobile) === String(mobile));
      return order ? { success: true, order } : { success: false, message: 'Order not found' };
    }

    const response = await fetch(`/.netlify/functions/track-order?orderId=${encodeURIComponent(orderId)}&mobile=${encodeURIComponent(mobile)}`);
    const data = await readJsonResponse(response, 'Tracking service');
    return data;
  } catch (error) {
    console.error('Error tracking order:', error);
    return { success: false, message: error.message };
  }
}

async function readJsonResponse(response, serviceName) {
  const responseText = await response.text();
  let data;
  try {
    data = JSON.parse(responseText);
  } catch (error) {
    throw new Error(`${serviceName} returned HTTP ${response.status} instead of JSON. Redeploy Netlify Functions and check Netlify configuration.`);
  }
  if (!response.ok) throw new Error(data.message || `${serviceName} returned HTTP ${response.status}`);
  return data;
}

// Admin login
export async function adminLogin(username, password) {
  try {
    const response = await fetch('/.netlify/functions/admin-login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ username, password })
    });
    
    const data = await readJsonResponse(response, 'Admin service');
    if (data.success) {
      localStorage.setItem('adminToken', data.token);
      return { success: true };
    }
    return { success: false, message: data.message };
  } catch (error) {
    console.error('Error logging in:', error);
    return { success: false, message: error.message };
  }
}

// Check if user is admin
export function isAdmin() {
  return !!localStorage.getItem('adminToken');
}

export { database, auth };
