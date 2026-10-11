<?php
$pageTitle = 'Product Details - LookStylo Clothing';
require_once __DIR__ . '/../app/components/functions.php';
include __DIR__ . '/../app/components/header.php';
?>

<main class="max-w-7xl mx-auto p-6">
    <div id="productContainer" class="bg-white rounded-lg shadow p-6"></div>
</main>

<script type="module" src="../app/services/product.js?v=2"></script>

<?php require_once __DIR__ . '/../app/components/footer.php'; ?>
