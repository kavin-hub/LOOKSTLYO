// netlify/functions/get-products.js
// Returns active products from Supabase.
import { jsonResponse, supabaseRequest } from './_supabase.js';

export const handler = async (event) => {
  try {
    const fallbackProducts = [
      {
        id: 1,
        name: "Premium Oversized T-Shirt",
        slug: "premium-oversized-tshirt",
        price: 499,
        discount_percent: 20,
        image: "IMG101.jpeg",
        description: "High-quality oversized t-shirt perfect for streetwear",
        category: "t-shirts"
      },
      {
        id: 2,
        name: "Baggy T-Shirt Black",
        slug: "baggy-tshirt-black",
        price: 599,
        discount_percent: 15,
        image: "IMG102.jpeg",
        description: "Trendy baggy fit t-shirt in black",
        category: "t-shirts"
      },
      {
        id: 3,
        name: "Football Jersey",
        slug: "football-jersey",
        price: 799,
        discount_percent: 10,
        image: "IMG103.jpeg",
        description: "Sports-style football jersey",
        category: "jerseys"
      },
      {
        id: 4,
        name: "Track Pants",
        slug: "track-pants",
        price: 899,
        discount_percent: 25,
        image: "IMG104.jpeg",
        description: "Comfortable track pants with jogger style",
        category: "pants"
      }
    ];

    let products;
    try {
      products = await supabaseRequest('products?active=eq.true&select=id,name,slug,description,category,price,discount_percent,image&order=created_at.desc');
    } catch (error) {
      products = fallbackProducts;
    }

    return jsonResponse(200, { success: true, products: products?.length ? products : fallbackProducts });
  } catch (error) {
    return {
      statusCode: 500,
      body: JSON.stringify({
        success: false,
        message: "Error fetching products",
        error: error.message
      })
    };
  }
};
