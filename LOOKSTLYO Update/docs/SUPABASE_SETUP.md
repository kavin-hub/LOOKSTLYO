# LookStylo Supabase Setup

The Netlify Functions now use Supabase for the catalogue and order system.

## 1. Create the database

1. Create a project at https://supabase.com.
2. Open **SQL Editor**.
3. Run [`supabase-schema.sql`](supabase-schema.sql).
4. Add products in the `products` table.
5. Add size/color quantities in the `inventory` table.

## 2. Configure Netlify

Add these environment variables in **Site configuration > Environment variables**:

```text
SUPABASE_URL=https://YOUR_PROJECT_REF.supabase.co
SUPABASE_SERVICE_ROLE_KEY=YOUR_SERVER_ONLY_SERVICE_ROLE_KEY
ADMIN_TOKEN_SECRET=LONG_RANDOM_SECRET
```

Never put `SUPABASE_SERVICE_ROLE_KEY` in frontend JavaScript or commit it to GitHub. Netlify Functions use it server-side and RLS remains enabled for the tables.

## 3. Data flow

- `products`: catalogue items shown by `shop.html`.
- `inventory`: stock by product, size, and color.
- `customers`: customer contact and delivery details.
- `orders`: order totals, payment information, and current status.
- `order_items`: immutable product snapshots for each order.
- `order_status_history`: payment and delivery timeline entries.

Checkout creates the customer, order, order items, and initial `Payment Verification Pending` history record. The admin dashboard changes status through `Payment Verified`, `Dispatched`, `Out for Delivery`, and `Delivered`.

## 4. Deploy

Connect the GitHub repository to Netlify. Netlify will publish the static site and deploy `netlify/functions`. GitHub Pages can show the frontend, but it cannot run these functions or persist orders.

After changing `netlify.toml` or a function, trigger a fresh Netlify deploy. Confirm that `https://YOUR_SITE.netlify.app/.netlify/functions/create-order` is reachable through the deployed site; a missing function should return a JSON/HTTP error, not the homepage HTML.
