<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'LookStylo Clothing'; ?></title>
    <meta name="description" content="LookStylo Clothing premium men's fashion, streetwear and custom clothing.">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .shadow-soft { box-shadow: 0 18px 40px rgba(48, 16, 64, 0.10); }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
<nav class="bg-white shadow-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20 items-center">
            <a href="../index.html" class="flex items-center gap-3">
                <img src="../assets/images/slazzer-preview-x2n1h.png" alt="LookStylo logo" class="h-14 w-auto object-contain">
                <div>
                    <div class="text-lg font-bold text-[#301040]">LOOKSTYLO</div>
                    <div class="text-xs uppercase tracking-[0.25em] text-gray-500">Premium Fashion</div>
                </div>
            </a>
            <div class="flex items-center gap-3">
                <a href="shop.php" class="hidden md:inline-flex items-center rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-purple-50 hover:text-purple-900">Shop</a>
                <a href="cart.php" class="inline-flex items-center gap-2 rounded-full bg-[#301040] px-4 py-2 text-sm font-semibold text-white shadow-lg hover:bg-purple-900">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l2.4 8.4a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L17.5 6H7"></path><circle cx="9" cy="20" r="1"></circle><circle cx="19" cy="20" r="1"></circle></svg>
                    Cart <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs"><?php echo getCartCount(getDbConnection()); ?></span>
                </a>
                <a href="my-orders.php" class="hidden md:inline-flex items-center rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-purple-50 hover:text-purple-900">My Orders</a>
                <a href="track-order.php" class="hidden md:inline-flex items-center rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-purple-50 hover:text-purple-900">Track Order</a>
            </div>
        </div>
    </div>
</nav>
