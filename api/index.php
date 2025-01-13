<?php
require_once 'config.php';

// Default domain uzantıları
$defaultTlds = ['.com', '.net', '.org', '.io', '.co', '.app'];

// Kullanıcı tanımlı domain uzantılarını session'dan al
session_start();
$customTlds = isset($_SESSION['custom_tlds']) ? $_SESSION['custom_tlds'] : [];

// Cache kontrolü için header'lar
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$error = '';
$results = [];
$apiDebug = [];

// AJAX isteğini kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'search') {
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        if (!empty($domain)) {
            // Domain adından uzantıyı çıkar
            $domainName = preg_replace('/\.[a-z]{2,}$/', '', $domain);
            
            // Tüm uzantılarla domain listesi oluştur
            $domainList = array_map(function($tld) use ($domainName) {
                return $domainName . $tld;
            }, array_merge($defaultTlds, $customTlds));

            $apiUrl = WHOIS_API_ENDPOINT . '?domain=' . urlencode(implode(',', $domainList)) . '&sse=true&return_dates=true&return-prices=true';
            
            // API Debug bilgilerini ekle
            $debug = [
                'request' => [
                    'url' => $apiUrl,
                    'domains' => $domainList,
                    'time' => date('Y-m-d H:i:s')
                ]
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HEADER, true);
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                $debug['error'] = curl_error($ch);
                echo json_encode(['success' => false, 'error' => curl_error($ch), 'debug' => $debug]);
            } else {
                $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $responseHeader = substr($response, 0, $headerSize);
                $responseBody = substr($response, $headerSize);

                // SSE yanıtını işle
                $lines = explode("\n", $responseBody);
                $results = [];
                $currentDomain = null;
                
                foreach ($lines as $line) {
                    if (empty($line)) continue;
                    
                    if (strpos($line, 'data: ') === 0) {
                        $jsonData = json_decode(substr($line, 6), true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['domain'])) {
                            $currentDomain = $jsonData['domain'];
                            if (!isset($results[$currentDomain])) {
                                $results[$currentDomain] = [
                                    'meta' => [
                                        'existed' => $jsonData['meta']['existed'] ?? null,
                                        'price' => $jsonData['meta']['price'] ?? null
                                    ]
                                ];
                            } else {
                                if (isset($jsonData['meta'])) {
                                    foreach ($jsonData['meta'] as $key => $value) {
                                        $results[$currentDomain]['meta'][$key] = $value;
                                    }
                                }
                            }
                        }
                    }
                }

                $debug['response'] = [
                    'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
                    'total_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
                    'headers' => $responseHeader,
                    'parsed_domains' => array_keys($results)
                ];

                echo json_encode([
                    'success' => true, 
                    'results' => $results,
                    'debug' => $debug
                ]);
            }
            curl_close($ch);
            exit;
        }
    } elseif ($_POST['action'] === 'save_tlds') {
        $newTlds = isset($_POST['tlds']) ? array_filter(array_map('trim', explode("\n", $_POST['tlds']))) : [];
        $_SESSION['custom_tlds'] = $newTlds;
        echo json_encode(['success' => true]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>whois.app - Domain Sorgulama</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .logo-whois {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logo-dot {
            color: #3B82F6;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
        }
        .logo-app {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            color: #64748B;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <h1 class="text-2xl hidden sm:flex items-center tracking-tight">
                        <span class="logo-whois">whois</span><span class="logo-dot">.</span><span class="logo-app">app</span>
                    </h1>
                    <!-- Mobil Logo -->
                    <h1 class="text-2xl sm:hidden flex items-center tracking-tight">
                        <span class="logo-whois">whois</span><span class="logo-dot">.</span><span class="logo-app">app</span>
                    </h1>
                </div>
                <nav class="flex items-center space-x-4">
                    <button class="sm:hidden text-gray-600 hover:text-gray-800">
                        <i class="fas fa-bars"></i>
                    </button>
                </nav>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden">
        <div class="bg-white w-64 h-full ml-auto">
            <div class="p-4 border-b">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Menü</h2>
                    <button id="closeMobileMenu" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <nav class="p-4">
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Hero Section -->
        <div class="text-center mb-12">
            <div class="inline-block px-4 py-2 bg-gradient-to-r from-indigo-50 to-blue-50 rounded-full text-sm font-medium text-indigo-600 mb-4">
                Hızlı, Doğru, Detaylı
            </div>
            <div class="flex items-center justify-center mb-4">
                <h1 class="text-3xl sm:text-4xl flex items-center tracking-tight">
                    <span class="logo-whois">whois</span><span class="logo-dot">.</span><span class="logo-app">app</span>
                </h1>
            </div>
            <p class="text-lg sm:text-xl text-gray-600 px-4">Toplu Domain Sorgulama Servisi</p>
        </div>

        <!-- Search Box -->
        <div class="max-w-4xl mx-auto mb-8 px-4">
            <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-shadow p-4 sm:p-6">
                <div class="relative">
                    <input
                        type="text"
                        id="domain"
                        class="w-full px-4 py-3 pr-12 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                        placeholder="Domain adı girin..."
                        autocomplete="off"
                    >
                    <button 
                        id="settingsBtn"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                        title="Domain Uzantılarını Özelleştir"
                    >
                        <i class="fas fa-cog"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Feature Cards -->
        <div id="featureCards" class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8 px-4 mb-12">
            <!-- Card 1 -->
            <div class="bg-white rounded-2xl p-6 text-center">
                <div class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-search text-blue-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Toplu Domain Sorgulama</h3>
                <p class="text-gray-600">Birden fazla domain uzantısını tek seferde sorgulayarak, hangilerinin tescil edilebilir olduğunu anında öğrenin.</p>
            </div>

            <!-- Card 2 -->
            <div class="bg-white rounded-2xl p-6 text-center">
                <div class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-shield-alt text-blue-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Detaylı WHOIS Bilgileri</h3>
                <p class="text-gray-600">Domain'in kayıt tarihi, DNS sunucuları, registrar bilgileri ve daha fazlasına anında erişin.</p>
            </div>

            <!-- Card 3 -->
            <div class="bg-white rounded-2xl p-6 text-center">
                <div class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-bolt text-blue-500 text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Hızlı ve Güvenilir</h3>
                <p class="text-gray-600">Gelişmiş altyapımız sayesinde saniyeler içinde doğru ve güncel domain bilgilerine ulaşın.</p>
            </div>
        </div>

        <!-- Results Area -->
        <div id="resultsContainer" class="hidden animate-fade-in">
            <div class="max-w-4xl mx-auto px-4">
                <div class="flex flex-col lg:flex-row gap-6 transition-all duration-300">
                    <!-- Left Column - Domain List -->
                    <div id="resultsColumn" class="w-full transition-all duration-300">
                        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Sonuçlar</h3>
                                <span id="resultCount" class="px-3 py-1 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-full text-sm font-medium text-blue-600"></span>
                            </div>
                            <div id="results" class="space-y-3">
                                <div class="flex items-center justify-center h-32 text-gray-500">
                                    <div class="text-center">
                                        <i class="fas fa-search text-2xl mb-2"></i>
                                        <p>Domain adı girin ve sonuçları görün</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Domain Details -->
                    <div id="detailsColumn" class="w-0 opacity-0 transition-all duration-300 lg:block">
                        <div id="domainDetails" class="bg-white rounded-2xl shadow-lg p-4 sm:p-6">
                            <div class="flex items-center justify-center h-64">
                                <div class="text-center text-gray-500">
                                    <i class="fas fa-mouse-pointer text-2xl mb-2"></i>
                                    <p>Detayları görüntülemek için<br>bir domain seçin</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Modal -->
        <div id="settingsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
            <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Domain Uzantıları</h3>
                    <button id="closeModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mb-4">
                        <div class="grid grid-cols-3 gap-3" id="tldCheckboxes">
                            <?php
                        // Tüm uzantılar
                        $defaultTlds = [
                            'com', 'net', 'org', 'io', 'co', 'app'
                        ];
                        
                            $availableTlds = [
                            'ai', 'cn', 'info', 'xyz', 'run', 'me', 
                            'pro', 'top', 'online', 'tools', 'link'
                        ];
                        
                        // Önce default TLD'leri göster (seçili ve disabled)
                        foreach ($defaultTlds as $tld): 
                        ?>
                            <label class="flex items-center p-2 border rounded-lg bg-gray-50">
                                <input 
                                    type="checkbox" 
                                    checked
                                    disabled
                                    class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500 cursor-not-allowed"
                                >
                                <span class="ml-2 text-sm font-medium text-gray-700">.<?php echo $tld; ?></span>
                            </label>
                        <?php endforeach; ?>

                        <!-- Diğer uzantılar -->
                        <?php
                            foreach ($availableTlds as $tld): 
                                $isChecked = in_array('.' . $tld, $customTlds);
                            ?>
                                <label class="flex items-center p-2 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="tlds[]" 
                                        value="<?php echo $tld; ?>"
                                        <?php echo $isChecked ? 'checked' : ''; ?>
                                        class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500"
                                    >
                                    <span class="ml-2 text-sm font-medium text-gray-700">.<?php echo $tld; ?></span>
                                </label>
                            <?php endforeach; ?>
                    </div>
                </div>
                <button id="saveTlds" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-indigo-700 transition-colors">
                    Kaydet
                </button>
            </div>
        </div>

        <!-- FAQ Section -->
        <div id="faqSection" class="max-w-4xl mx-auto px-4 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 tracking-tight">SSS</h2>
            <div class="space-y-4">
                <?php
                require_once 'Markdown.php';
                $markdown = file_get_contents('faq.md');
                $lines = explode("\n", $markdown);
                $questions = [];
                $currentQuestion = null;
                $currentAnswer = [];

                foreach ($lines as $line) {
                    if (strpos($line, '### ') === 0) {
                        if ($currentQuestion !== null) {
                            $questions[] = [
                                'question' => $currentQuestion,
                                'answer' => implode("\n", $currentAnswer)
                            ];
                            $currentAnswer = [];
                        }
                        $currentQuestion = substr($line, 4);
                    } elseif ($currentQuestion !== null && !empty(trim($line))) {
                        $currentAnswer[] = $line;
                    }
                }

                if ($currentQuestion !== null) {
                    $questions[] = [
                        'question' => $currentQuestion,
                        'answer' => implode("\n", $currentAnswer)
                    ];
                }

                foreach ($questions as $index => $qa): ?>
                    <div class="bg-white rounded-xl overflow-hidden transition-all duration-300">
                        <button class="faq-toggle w-full px-6 py-4 text-left bg-white hover:bg-gray-50 transition-colors flex items-center justify-between group" data-target="faq-<?php echo $index; ?>">
                            <div class="flex items-center space-x-4">
                                <span class="text-indigo-600 text-sm font-medium">0<?php echo $index + 1; ?></span>
                                <span class="text-gray-900 font-medium group-hover:text-indigo-600 transition-colors"><?php echo htmlspecialchars($qa['question']); ?></span>
                            </div>
                            <i class="fas fa-chevron-right text-gray-400 transition-transform duration-300 group-hover:text-indigo-600"></i>
                        </button>
                        <div id="faq-<?php echo $index; ?>" class="faq-content hidden">
                            <div class="px-6 py-4 border-t">
                                <p class="text-gray-600"><?php echo htmlspecialchars($qa['answer']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-12 py-6 text-center text-gray-500 text-sm">
            <p>&copy; <?php echo date('Y'); ?> whois.app - Tüm hakları saklıdır.</p>
        </footer>

        <!-- Domain Details Modal (Mobile) -->
        <div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto relative">
                <button onclick="closeDetails()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
                <div id="mobileDetails">
                    <div class="flex items-center justify-center h-64">
                        <div class="text-center text-gray-500">
                            <i class="fas fa-mouse-pointer text-2xl mb-2"></i>
                            <p>Detayları görüntülemek için<br>bir domain seçin</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            @media (max-width: 1024px) {
                #detailsColumn {
                    display: none !important;
                }

                #detailsModal.active {
                    display: flex !important;
                }

                #detailsModal .bg-white {
                    margin-top: 1rem;
                    margin-bottom: 1rem;
                }
            }

            @media (min-width: 1025px) {
                #detailsModal {
                    display: none !important;
                }
            }

            .animate-fade-in {
                animation: fadeIn 0.3s ease-in-out;
            }
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            #results:empty::after {
                content: "Sonuç bulunamadı";
                display: block;
                text-align: center;
                color: #6b7280;
                padding: 2rem 0;
            }

            .column-expanded {
                width: 100% !important;
                opacity: 1 !important;
            }

            .column-half {
                width: 50% !important;
                opacity: 1 !important;
            }

            .column-hidden {
                width: 0 !important;
                opacity: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        </style>

        <div id="detailsOverlay"></div>

        <script>
            // Loglama yardımcı fonksiyonu
            function logDetails(title, data, type = 'info') {
                const styles = {
                    info: 'color: #2563eb; font-weight: bold;',
                    success: 'color: #059669; font-weight: bold;',
                    warning: 'color: #d97706; font-weight: bold;',
                    error: 'color: #dc2626; font-weight: bold;'
                };
                
                console.group(`%c${title}`, styles[type]);
                if (typeof data === 'object') {
                    console.table(data);
                } else {
                    console.log(data);
                }
                console.groupEnd();
            }

            // Domain detaylarını getir
            async function fetchDomainDetails(domain, isAvailable) {
                const isMobile = window.innerWidth < 1024;
                const detailsModal = document.getElementById('detailsModal');
                const targetElement = isMobile ? document.getElementById('mobileDetails') : document.getElementById('domainDetails');
                
                // Yükleniyor göstergesi
                const loadingHtml = `
                    <div class="flex items-center justify-center h-64">
                        <div class="text-center text-gray-500">
                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                            <p>Bilgiler yükleniyor...</p>
                        </div>
                    </div>
                `;

                targetElement.innerHTML = loadingHtml;

                if (isMobile) {
                    detailsModal.classList.add('active');
                } else {
                    const resultsColumn = document.getElementById('resultsColumn');
                    const detailsColumn = document.getElementById('detailsColumn');
                    
                    // Masaüstü görünümde display: none'ı kaldır
                    detailsColumn.style.display = 'block';
                    
                    // Sütun genişliklerini ayarla
                    resultsColumn.classList.remove('w-full');
                    resultsColumn.classList.add('column-half');
                    detailsColumn.classList.remove('w-0', 'opacity-0');
                    detailsColumn.classList.add('column-half');
                }

                try {
                    if (isAvailable) {
                        // Boşta olan domain için satın alma seçenekleri
                        targetElement.innerHTML = `
                            <h3 class="text-lg font-semibold mb-4">Satın Alma Seçenekleri</h3>
                            <div class="space-y-4">
                                <a href="https://godaddy.com/domainsearch/find?domainToCheck=${domain}" target="_blank" 
                                   class="block p-4 border rounded-lg hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">GoDaddy</span>
                                        <i class="fas fa-external-link-alt text-gray-400"></i>
                                    </div>
                                </a>
                                <a href="https://www.namecheap.com/domains/registration/results/?domain=${domain}" target="_blank"
                                   class="block p-4 border rounded-lg hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Namecheap</span>
                                        <i class="fas fa-external-link-alt text-gray-400"></i>
                                    </div>
                                </a>
                                <a href="https://domains.google.com/registrar/search?searchTerm=${domain}" target="_blank"
                                   class="block p-4 border rounded-lg hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">Google Domains</span>
                                        <i class="fas fa-external-link-alt text-gray-400"></i>
                                    </div>
                                </a>
                            </div>
                        `;
                    } else {
                        const response = await fetch(`https://instant.who.sb/api/v1/whois?domain=${domain}&cache=false&return-prices=false`);
                        const data = await response.json();
                        
                        if (data.parsed) {
                            const whoisInfo = data.parsed;
                            const nameservers = whoisInfo.nameservers ? whoisInfo.nameservers.split(',') : [];
                            const statusList = whoisInfo.status ? whoisInfo.status.split(',').map(s => s.split(' ')[0]) : [];
                            
                            // Kalan gün hesaplama
                            const today = new Date();
                            const expiryDate = new Date(whoisInfo.expires);
                            const remainingDays = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                            const remainingYears = Math.floor(remainingDays / 365);
                            
                            // Kalan süre metni oluşturma
                            let remainingTimeText = '';
                            let remainingTimeColor = '';
                            
                            if (remainingDays < 0) {
                                remainingTimeText = 'Süresi dolmuş';
                                remainingTimeColor = 'text-red-600 bg-red-50';
                            } else if (remainingDays <= 30) {
                                remainingTimeText = `${remainingDays} gün kaldı`;
                                remainingTimeColor = 'text-orange-600 bg-orange-50';
                            } else if (remainingDays <= 90) {
                                remainingTimeText = `${remainingDays} gün kaldı`;
                                remainingTimeColor = 'text-yellow-600 bg-yellow-50';
                            } else {
                                remainingTimeText = remainingYears > 0 
                                    ? `${remainingYears} yıl ${Math.floor((remainingDays % 365) / 30)} ay kaldı`
                                    : `${Math.floor(remainingDays / 30)} ay kaldı`;
                                remainingTimeColor = 'text-green-600 bg-green-50';
                            }
                            
                            const content = `
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <h3 class="text-lg font-semibold">WHOIS Bilgileri</h3>
                                            <a href="https://${domain}" target="_blank" class="text-gray-400 hover:text-gray-600" title="Siteyi ziyaret et">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="px-3 py-1 ${remainingTimeColor} rounded-full text-sm font-medium">
                                                ${remainingTimeText}
                                            </span>
                                            <span class="px-3 py-1 bg-gray-100 rounded-full text-sm text-gray-600">
                                                ${whoisInfo.suffix.toUpperCase()}
                                            </span>
                                        </div>
                                    </div>

                                    ${whoisInfo.registrar ? `
                                    <div class="p-4 border rounded-lg bg-white">
                                        <h4 class="font-medium text-gray-700 mb-2">Kayıt Firması</h4>
                                        <p class="text-gray-600">${whoisInfo.registrar}</p>
                                    </div>
                                    ` : ''}

                                    <div class="grid grid-cols-2 gap-4">
                                        ${whoisInfo.registered ? `
                                        <div class="p-4 border rounded-lg bg-white">
                                            <h4 class="font-medium text-gray-700 mb-2">Kayıt Tarihi</h4>
                                            <p class="text-gray-600">${new Date(whoisInfo.registered).toLocaleDateString('tr-TR')}</p>
                                        </div>
                                        ` : ''}

                                        ${whoisInfo.expires ? `
                                        <div class="p-4 border rounded-lg bg-white">
                                            <h4 class="font-medium text-gray-700 mb-2">Bitiş Tarihi</h4>
                                            <p class="text-gray-600">${new Date(whoisInfo.expires).toLocaleDateString('tr-TR')}</p>
                                        </div>
                                        ` : ''}
                                    </div>

                                    ${nameservers.length > 0 ? `
                                    <div class="p-4 border rounded-lg bg-white">
                                        <h4 class="font-medium text-gray-700 mb-2">Nameserverlar</h4>
                                        <div class="space-y-1">
                                            ${nameservers.map(ns => `
                                                <div class="flex items-center space-x-2">
                                                    <i class="fas fa-server text-gray-400"></i>
                                                    <span class="text-gray-600">${ns.trim()}</span>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                    ` : ''}

                                    <div class="p-4 border rounded-lg bg-white">
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="font-medium text-gray-700">Raw WHOIS</h4>
                                            <button onclick="copyRawWhois(this)" class="text-gray-400 hover:text-gray-600" title="Kopyala">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <pre class="text-sm text-gray-600 whitespace-pre-wrap font-mono bg-gray-50 p-3 rounded-lg max-h-64 overflow-y-auto">${data.raw}</pre>
                                    </div>
                                </div>
                            `;
                            targetElement.innerHTML = content;
                        } else {
                            const errorHtml = `
                                <div class="flex items-center justify-center h-64">
                                    <div class="text-center text-red-500">
                                        <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                                        <p>WHOIS bilgisi alınamadı.</p>
                                    </div>
                                </div>
                            `;
                            targetElement.innerHTML = errorHtml;
                        }
                    }
                } catch (error) {
                    console.error('Domain detayları alınırken hata:', error);
                    const errorHtml = `
                        <div class="flex items-center justify-center h-64">
                            <div class="text-center text-red-500">
                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                <p>Bir hata oluştu.<br>Lütfen tekrar deneyin.</p>
                            </div>
                        </div>
                    `;
                    targetElement.innerHTML = errorHtml;
                }
            }

            // Raw WHOIS kopyalama fonksiyonu
            function copyRawWhois(button) {
                const preElement = button.parentElement.nextElementSibling;
                const text = preElement.textContent;
                
                navigator.clipboard.writeText(text).then(() => {
                    const originalIcon = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-check text-green-500"></i>';
                    button.title = 'Kopyalandı!';
                    
                    setTimeout(() => {
                        button.innerHTML = originalIcon;
                        button.title = 'Kopyala';
                    }, 2000);
                }).catch(err => {
                    console.error('Kopyalama hatası:', err);
                    button.innerHTML = '<i class="fas fa-times text-red-500"></i>';
                    button.title = 'Kopyalama başarısız';
                    
                    setTimeout(() => {
                        button.innerHTML = originalIcon;
                        button.title = 'Kopyala';
                    }, 2000);
                });
            }

            let searchTimeout;
            const domain = document.getElementById('domain');
            const results = document.getElementById('results');
            const settingsBtn = document.getElementById('settingsBtn');
            const settingsModal = document.getElementById('settingsModal');
            const closeModal = document.getElementById('closeModal');
            const saveTlds = document.getElementById('saveTlds');

            // Domain arama fonksiyonu
            async function searchDomain(value) {
                try {
                    // Feature Cards'ı gizle
                    document.getElementById('featureCards').style.display = 'none';
                    
                    // Sonuçlar alanını göster
                    document.getElementById('resultsContainer').classList.remove('hidden');
                    
                    // Detay panelini sıfırla
                    const detailsModal = document.getElementById('detailsModal');
                    const detailsColumn = document.getElementById('detailsColumn');
                    const resultsColumn = document.getElementById('resultsColumn');
                    
                    // Mobil/masaüstü kontrolü
                    const isMobile = window.innerWidth < 1024;
                    
                    if (isMobile) {
                        detailsModal.classList.remove('active');
                    } else {
                        // Masaüstünde tam genişliğe dön
                        resultsColumn.classList.remove('column-half');
                        resultsColumn.classList.add('w-full');
                        detailsColumn.classList.remove('column-half');
                        detailsColumn.classList.add('w-0', 'opacity-0');
                    }
                    
                    // Yükleniyor animasyonu
                    results.innerHTML = `
                        <div class="flex items-center justify-center h-32">
                            <div class="text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                <p>Domainler sorgulanıyor...</p>
                            </div>
                        </div>
                    `;

                    const formData = new FormData();
                    formData.append('action', 'search');
                    formData.append('domain', value);

                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success && data.results) {
                        const resultEntries = Object.entries(data.results);
                        
                        // Sonuç sayısını güncelle
                        document.getElementById('resultCount').textContent = `${resultEntries.length} sonuç`;
                        
                        results.innerHTML = resultEntries
                            .map(([domain, info]) => {
                                const isAvailable = info.meta.existed === 'no';
                                const price = info.meta.price;
                                const registrationDate = !isAvailable && info.meta.registered ? new Date(info.meta.registered).getFullYear() : null;
                                const isReserved = info.meta.type === 0 || info.meta.type === '0';
                                
                                // Debug için
                                console.log('Domain:', domain, 'Info:', info.meta);
                                
                                let statusText = '';
                                if (isAvailable) {
                                    statusText = price ? price + ' USD' : '';
                                } else if (isReserved) {
                                    statusText = 'Reserved';
                                } else if (registrationDate) {
                                    statusText = registrationDate;
                                } else {
                                    statusText = 'Gizli';
                                }
                                
                                return `
                                    <div class="flex items-center justify-between p-4 rounded-lg border ${isAvailable ? 'border-green-200 bg-green-50' : 'border-gray-200'} hover:shadow-md transition-shadow cursor-pointer"
                                         onclick="fetchDomainDetails('${domain}', ${isAvailable})">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-6 h-6 flex items-center justify-center">
                                                <i class="fas ${isAvailable ? 'fa-check text-green-500' : 'fa-times text-gray-400'}"></i>
                                            </div>
                                            <span class="font-medium ${isAvailable ? 'text-green-600' : 'text-gray-600'}">
                                                ${domain}
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-3">
                                            <div class="text-xs text-gray-500">
                                                ${statusText}
                                            </div>
                                        </div>
                                    </div>
                                `;
                            })
                            .join('');
                    } else {
                        results.innerHTML = `
                            <div class="flex items-center justify-center h-32">
                                <div class="text-center text-red-500">
                                    <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                                    <p>Sonuç alınamadı.<br>Lütfen tekrar deneyin.</p>
                                </div>
                            </div>
                        `;
                    }

                    // Mobil görünümde detay panelini kapat
                    if (window.innerWidth < 1024) {
                        document.getElementById('detailsColumn').classList.remove('active');
                        document.getElementById('detailsOverlay').classList.remove('active');
                    }
                } catch (error) {
                    console.error('Arama hatası:', error);
                    results.innerHTML = `
                        <div class="flex items-center justify-center h-32">
                            <div class="text-center text-red-500">
                                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                <p>Bir hata oluştu.<br>Lütfen tekrar deneyin.</p>
                            </div>
                        </div>
                    `;
                }
            }

            // Domain input olayları
            domain.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const value = e.target.value.trim();
                
                if (value.length > 0) {
                    searchTimeout = setTimeout(() => searchDomain(value), 500);
                } else {
                    // Sonuçlar alanını gizle
                    document.getElementById('resultsContainer').classList.add('hidden');
                    // Feature Cards'ı göster
                    document.getElementById('featureCards').style.display = 'grid';
                    document.getElementById('resultsColumn').classList.remove('column-half');
                    document.getElementById('detailsColumn').classList.remove('column-half');
                    results.innerHTML = '';
                }
            });

            // Modal olayları
            settingsBtn.addEventListener('click', () => {
                settingsModal.style.display = 'flex';
            });

            closeModal.addEventListener('click', () => {
                settingsModal.style.display = 'none';
            });

            settingsModal.addEventListener('click', (e) => {
                if (e.target === settingsModal) {
                    settingsModal.style.display = 'none';
                }
            });

            // Özel uzantıları kaydet
            saveTlds.addEventListener('click', async () => {
                try {
                    const checkboxes = document.querySelectorAll('input[name="tlds[]"]:checked');
                    const selectedTlds = Array.from(checkboxes).map(cb => '.' + cb.value);

                    const formData = new FormData();
                    formData.append('action', 'save_tlds');
                    formData.append('tlds', selectedTlds.join('\n'));

                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        settingsModal.style.display = 'none';
                        // Mevcut aramanın sonuçlarını güncelle
                        const currentDomain = domain.value.trim();
                        if (currentDomain) {
                            searchDomain(currentDomain);
                        }
                    }
                } catch (error) {
                    console.error('Kaydetme hatası:', error);
                }
            });

            // Mobil menü işlemleri
            const mobileMenuBtn = document.querySelector('.sm\\:hidden');
            const mobileMenu = document.getElementById('mobileMenu');
            const closeMobileMenu = document.getElementById('closeMobileMenu');

            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.remove('hidden');
            });

            closeMobileMenu.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
            });

            mobileMenu.addEventListener('click', (e) => {
                if (e.target === mobileMenu) {
                    mobileMenu.classList.add('hidden');
                }
            });

            // Overlay tıklama olayı
            document.getElementById('detailsOverlay').addEventListener('click', () => {
                const detailsColumn = document.getElementById('detailsColumn');
                const detailsOverlay = document.getElementById('detailsOverlay');
                
                detailsColumn.classList.remove('active');
                detailsOverlay.classList.remove('active');
                
                setTimeout(() => {
                    if (!detailsColumn.classList.contains('active')) {
                        detailsColumn.style.display = 'none';
                    }
                }, 300);
            });

            // Pencere boyutu değiştiğinde kontrol
            window.addEventListener('resize', () => {
                const detailsColumn = document.getElementById('detailsColumn');
                const detailsOverlay = document.getElementById('detailsOverlay');
                const isMobile = window.innerWidth < 1024;
                
                if (!isMobile) {
                    detailsColumn.classList.remove('active');
                    detailsOverlay.classList.remove('active');
                }
            });

            // Detay modalını kapat
            function closeDetails() {
                const detailsModal = document.getElementById('detailsModal');
                detailsModal.classList.remove('active');
            }

            // Modal dışına tıklanınca kapat
            document.getElementById('detailsModal').addEventListener('click', (e) => {
                if (e.target === e.currentTarget) {
                    closeDetails();
                }
            });

            // FAQ Akordiyon
            document.querySelectorAll('.faq-toggle').forEach(button => {
                button.addEventListener('click', () => {
                    const targetId = button.getAttribute('data-target');
                    const content = document.getElementById(targetId);
                    const icon = button.querySelector('i');
                    const allButtons = document.querySelectorAll('.faq-toggle');
                    
                    // Diğer tüm açık içerikleri kapat
                    document.querySelectorAll('.faq-content').forEach(item => {
                        if (item.id !== targetId && !item.classList.contains('hidden')) {
                            item.classList.add('hidden');
                            const otherIcon = item.previousElementSibling.querySelector('i');
                            otherIcon.style.transform = 'rotate(0deg)';
                            otherIcon.classList.remove('text-indigo-600');
                        }
                    });
                    
                    // Tıklanan içeriği aç/kapat
                    content.classList.toggle('hidden');
                    
                    // İkon animasyonu
                    if (!content.classList.contains('hidden')) {
                        icon.style.transform = 'rotate(90deg)';
                        icon.classList.add('text-indigo-600');
                    } else {
                        icon.style.transform = 'rotate(0deg)';
                        icon.classList.remove('text-indigo-600');
                    }
                });
            });
        </script>
    </main>
</body>
</html> 