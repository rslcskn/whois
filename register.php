<?php
require_once 'config.php';

$domain = $_GET['domain'] ?? '';
if (empty($domain)) {
    header('Location: index.php');
    exit;
}

$extension = strtolower(pathinfo($domain, PATHINFO_EXTENSION));
$defaultPrice = match($extension) {
    'com' => DEFAULT_COM_PRICE,
    'net' => DEFAULT_NET_PRICE,
    'org' => DEFAULT_ORG_PRICE,
    default => DEFAULT_COM_PRICE
};

$registrars = [
    [
        'name' => 'GoDaddy',
        'logo' => 'https://logo.clearbit.com/godaddy.com',
        'url' => GODADDY_AFFILIATE_URL ?: 'https://godaddy.com/domainsearch/find',
        'price' => number_format($defaultPrice, 2)
    ],
    [
        'name' => 'Namecheap',
        'logo' => 'https://logo.clearbit.com/namecheap.com',
        'url' => NAMECHEAP_AFFILIATE_URL ?: 'https://www.namecheap.com/domains/registration/results',
        'price' => number_format($defaultPrice - 1, 2)
    ],
    [
        'name' => 'Google Domains',
        'logo' => 'https://logo.clearbit.com/domains.google',
        'url' => GOOGLE_DOMAINS_AFFILIATE_URL ?: 'https://domains.google.com/registrar/search',
        'price' => number_format($defaultPrice + 2, 2)
    ]
];

// AJAX isteği ise JSON döndür
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'domain' => $domain,
        'registrars' => $registrars
    ]);
    exit;
}

// Normal sayfa görüntüleme
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($domain); ?> - Domain Kayıt</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center mb-6">
                    <h1 class="text-2xl font-bold mb-2">
                        <?php echo htmlspecialchars($domain); ?>
                    </h1>
                    <div class="inline-flex items-center space-x-2">
                        <span class="px-3 py-1 bg-green-50 text-green-600 rounded-full text-sm font-medium">
                            Kayıt Edilebilir
                        </span>
                        <span class="px-3 py-1 bg-gray-50 text-gray-600 rounded-full text-sm">
                            <?php echo strtoupper(pathinfo($domain, PATHINFO_EXTENSION)); ?>
                        </span>
                    </div>
                </div>

                <div class="space-y-4">
                    <?php foreach ($registrars as $registrar): ?>
                        <a href="<?php echo htmlspecialchars($registrar['url']); ?>?domain=<?php echo urlencode($domain); ?>" 
                           target="_blank"
                           class="flex items-center justify-between p-4 border rounded hover:shadow-lg transition-shadow cursor-pointer">
                            <div class="flex items-center space-x-4">
                                <img src="<?php echo htmlspecialchars($registrar['logo']); ?>" 
                                     alt="<?php echo htmlspecialchars($registrar['name']); ?>" 
                                     class="w-8 h-8 object-contain">
                                <span class="font-medium"><?php echo htmlspecialchars($registrar['name']); ?></span>
                            </div>
                            <div class="text-green-600 font-bold">
                                $<?php echo htmlspecialchars($registrar['price']); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="mt-6 text-center">
                    <a href="index.php" class="text-blue-500 hover:text-blue-700">
                        ← Sorgulamaya Geri Dön
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 