-- LookStylo Supabase schema
create extension if not exists pgcrypto;

create table if not exists public.customers (
    id uuid primary key default gen_random_uuid(),
    full_name text not null,
    mobile text not null unique,
    email text unique,
    password_hash text,
    address text,
    city text,
    state text,
    pincode text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create table if not exists public.products (
    id uuid primary key default gen_random_uuid(),
    name text not null,
    slug text unique,
    description text,
    category text,
    price numeric(12,2) not null default 0,
    discount_percent integer not null default 0,
    image text,
    active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create table if not exists public.inventory (
    id uuid primary key default gen_random_uuid(),
    product_id uuid not null references public.products(id) on delete cascade,
    size text not null default 'One Size',
    color text not null default 'Default',
    quantity integer not null default 0 check (quantity >= 0),
    updated_at timestamptz not null default now(),
    unique (product_id, size, color)
);

create table if not exists public.orders (
    id uuid primary key default gen_random_uuid(),
    order_id text not null unique,
    customer_id uuid not null references public.customers(id),
    total_amount numeric(12,2) not null default 0,
    status text not null default 'Payment Verification Pending' check (status in ('Payment Verification Pending', 'Payment Verified', 'Dispatched', 'Out for Delivery', 'Delivered', 'Cancelled')),
    address text not null,
    city text not null,
    state text not null,
    pincode text not null,
    order_notes text,
    utr_number text,
    payment_screenshot text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);

create table if not exists public.order_items (
    id uuid primary key default gen_random_uuid(),
    order_id uuid not null references public.orders(id) on delete cascade,
    product_id uuid references public.products(id) on delete set null,
    product_name text not null,
    size text,
    color text,
    quantity integer not null check (quantity > 0),
    unit_price numeric(12,2) not null default 0
);

create table if not exists public.order_status_history (
    id uuid primary key default gen_random_uuid(),
    order_id uuid not null references public.orders(id) on delete cascade,
    status text not null,
    note text,
    created_at timestamptz not null default now()
);

create index if not exists orders_customer_id_idx on public.orders(customer_id);
create index if not exists orders_status_idx on public.orders(status);
create index if not exists order_status_history_order_id_idx on public.order_status_history(order_id, created_at);
create index if not exists inventory_product_id_idx on public.inventory(product_id);

alter table public.customers enable row level security;
alter table public.products enable row level security;
alter table public.inventory enable row level security;
alter table public.orders enable row level security;
alter table public.order_items enable row level security;
alter table public.order_status_history enable row level security;

-- Netlify Functions use the Supabase service-role key server-side.
-- Do not expose that key in browser code.
