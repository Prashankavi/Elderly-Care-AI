<?php
// News Portal with State-based Organization and Indian Language Translation
// API key from NewsAPI.org
$api_key = "936cd85edb754d0cb3afdcb9bbb136a4";

// List of Indian states and major cities
$states = [
    'Andhra Pradesh (Vijayawada)', 'Arunachal Pradesh (Itanagar)', 'Assam (Guwahati)', 'Bihar (Patna)',
    'Chhattisgarh (Raipur)', 'Goa (Panaji)', 'Gujarat (Ahmedabad)', 'Haryana (Chandigarh)',
    'Himachal Pradesh (Shimla)', 'Jharkhand (Ranchi)', 'Karnataka (Bengaluru)', 'Kerala (Thiruvananthapuram)',
    'Madhya Pradesh (Bhopal)', 'Maharashtra (Mumbai)', 'Manipur (Imphal)', 'Meghalaya (Shillong)',
    'Mizoram (Aizawl)', 'Nagaland (Kohima)', 'Odisha (Bhubaneswar)', 'Punjab (Ludhiana)',
    'Rajasthan (Jaipur)', 'Sikkim (Gangtok)', 'Tamil Nadu (Chennai)', 'Telangana (Hyderabad)',
    'Tripura (Agartala)', 'Uttar Pradesh (Lucknow)', 'Uttarakhand (Dehradun)', 'West Bengal (Kolkata)',
    'Andaman and Nicobar Islands (Port Blair)', 'Chandigarh (Chandigarh)', 'Dadra and Nagar Haveli and Daman and Diu (Daman)',
    'Delhi (New Delhi)', 'Jammu and Kashmir (Srinagar)', 'Ladakh (Leh)', 'Lakshadweep (Kavaratti)', 'Puducherry (Pondicherry)'
];

// List of Indian languages with their codes for translation
$indian_languages = [
    'hi' => 'Hindi',
    'bn' => 'Bengali',
    'te' => 'Telugu',
    'ta' => 'Tamil',
    'mr' => 'Marathi',
    'gu' => 'Gujarati',
    'kn' => 'Kannada',
    'ml' => 'Malayalam',
    'pa' => 'Punjabi',
    'or' => 'Odia',
    'as' => 'Assamese',
    'en' => 'English'
];

// Default values
$selected_state = isset($_GET['state']) ? $_GET['state'] : 'Telangana (Hyderabad)';
$selected_language = isset($_GET['language']) ? $_GET['language'] : 'en';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Function to fetch news from NewsAPI
function fetchNews($api_key, $query, $page = 1, $pageSize = 10) {
    $url = "https://newsapi.org/v2/everything?q=" . urlencode($query) . 
           "&page=" . $page . 
           "&pageSize=" . $pageSize .
           "&language=en";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'NewsPortal/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Api-Key: ' . $api_key,
        'User-Agent: NewsPortal/1.0'
    ]);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        return ['error' => 'Curl error: ' . curl_error($ch)];
    }
    
    curl_close($ch);
    return json_decode($response, true);
}

// Function to translate text using Google Translate API
function translateText($text, $target_language) {
    if ($target_language == 'en') {
        return $text;
    }
    
    $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=" . 
           $target_language . "&dt=t&q=" . urlencode($text);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TranslationApp/1.0');
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        return $text;
    }
    
    curl_close($ch);
    
    $json = json_decode($response, true);
    $translated_text = '';
    
    if ($json && isset($json[0])) {
        foreach ($json[0] as $text_array) {
            if (isset($text_array[0])) {
                $translated_text .= $text_array[0];
            }
        }
    }
    
    return $translated_text ?: $text;
}

// Fetch news for the selected state
$news_data = fetchNews($api_key, $selected_state, $page);

// Check if we have any errors
$has_error = isset($news_data['error']) || (isset($news_data['status']) && $news_data['status'] == 'error');
$error_message = $has_error ? ($news_data['error']['message'] ?? $news_data['message'] ?? 'Unknown error') : '';

// Calculate pagination
$total_results = $has_error ? 0 : ($news_data['totalResults'] ?? 0);
$total_pages = ceil($total_results / 10);

// Function to sanitize output
function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>INDIA Country News Portal with Language Translation</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(78, 115, 223, 0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .header h1 {
            font-size: 2.5rem;
            color: #4e73df;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 1.1rem;
            color: #666;
            position: relative;
            z-index: 1;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: #4e73df;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .back-btn:hover {
            background: #2e59d9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
            color: white;
            text-decoration: none;
        }

        /* Main Layout */
        .main-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        /* Sidebar Styles */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .filter-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .filter-header {
            background: linear-gradient(135deg, #4e73df, #764ba2);
            padding: 20px;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .filter-content {
            max-height: 300px;
            overflow-y: auto;
            padding: 0;
        }

        .filter-item {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
        }

        .filter-item:hover {
            background: rgba(78, 115, 223, 0.1);
            color: #4e73df;
            transform: translateX(5px);
        }

        .filter-item.active {
            background: linear-gradient(135deg, #4e73df, #764ba2);
            color: white;
        }

        .filter-item.active::after {
            content: '✓';
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* News Content Area */
        .news-content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .news-header {
            background: linear-gradient(135deg, #4e73df, #764ba2);
            padding: 25px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .news-title {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .language-info {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .news-body {
            padding: 30px;
        }

        /* Error and Info Messages */
        .alert {
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        .alert-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        /* News Grid */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .news-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .news-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .news-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .news-card:hover .news-image {
            transform: scale(1.05);
        }

        .news-card-body {
            padding: 25px;
        }

        .news-card-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 15px;
            color: #4e73df;
            line-height: 1.4;
        }

        .news-card-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .news-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #888;
        }

        .news-card-footer {
            padding: 0 25px 25px;
        }

        .read-more-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #4e73df, #764ba2);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .read-more-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(78, 115, 223, 0.3);
            color: white;
            text-decoration: none;
        }

        .read-more-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .read-more-btn:hover::before {
            left: 100%;
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        .pagination {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .page-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.9);
            color: #4e73df;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .page-btn:hover {
            background: #4e73df;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(78, 115, 223, 0.3);
            text-decoration: none;
        }

        .page-btn.active {
            background: linear-gradient(135deg, #4e73df, #764ba2);
            color: white;
        }

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .page-nav-btn {
            padding: 0 20px;
            width: auto;
            min-width: 80px;
        }

        /* Footer */
        .footer {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .footer h3 {
            color: #4e73df;
            margin-bottom: 15px;
        }

        .footer p {
            color: #666;
            margin-bottom: 10px;
        }

        /* Scroll to Top Button */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #4e73df;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
        }

        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top:hover {
            background: #2e59d9;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(78, 115, 223, 0.4);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .back-btn {
                position: static;
                margin-bottom: 20px;
                align-self: flex-start;
            }

            .main-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .sidebar {
                order: 2;
            }

            .news-content {
                order: 1;
            }

            .news-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .news-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 8px;
            }

            .page-btn {
                width: 40px;
                height: 40px;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(78, 115, 223, 0.3);
            border-radius: 50%;
            border-top-color: #4e73df;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <a href="../dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <h1>🇮🇳 INDIA Country News Portal</h1>
            <p>News from across India with multilingual translation support</p>
        </div>

        <!-- Main Layout -->
        <div class="main-layout">
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- State Filter -->
                <div class="filter-card">
                    <div class="filter-header">
                        <i class="fas fa-map-marker-alt"></i> Select State
                    </div>
                    <div class="filter-content">
                        <?php foreach ($states as $state): ?>
                            <a href="?state=<?= urlencode($state) ?>&language=<?= h($selected_language) ?>" 
                               class="filter-item <?= $selected_state == $state ? 'active' : '' ?>">
                                <?= h($state) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Language Filter -->
                <div class="filter-card">
                    <div class="filter-header">
                        <i class="fas fa-language"></i> Select Language
                    </div>
                    <div class="filter-content">
                        <?php foreach ($indian_languages as $code => $language): ?>
                            <a href="?state=<?= urlencode($selected_state) ?>&language=<?= $code ?>" 
                               class="filter-item <?= $selected_language == $code ? 'active' : '' ?>">
                                <?= h($language) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- News Content -->
            <div class="news-content">
                <div class="news-header">
                    <div class="news-title">
                        <i class="fas fa-newspaper"></i> <?= h($selected_state) ?> News
                    </div>
                    <div class="language-info">
                        <i class="fas fa-globe"></i> <?= h($indian_languages[$selected_language] ?? 'English') ?>
                    </div>
                </div>
                
                <div class="news-body">
                    <?php if ($has_error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Error fetching news:</strong><br>
                                <?= h($error_message) ?><br>
                                Please check your API key or try again later.
                            </div>
                        </div>
                    <?php elseif (empty($news_data['articles'])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                No news found for <?= h($selected_state) ?>. Try another state or check back later.
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="news-grid">
                            <?php foreach ($news_data['articles'] as $article): ?>
                                <?php
                                    // Translate title and description
                                    $translated_title = translateText($article['title'], $selected_language);
                                    $translated_description = translateText($article['description'], $selected_language);
                                    
                                    // Format date
                                    $published_date = new DateTime($article['publishedAt']);
                                    $formatted_date = $published_date->format('F j, Y, g:i a');
                                ?>
                                <div class="news-card">
                                    <?php if ($article['urlToImage']): ?>
                                        <img src="<?= h($article['urlToImage']) ?>" 
                                             class="news-image" 
                                             alt="<?= h($article['title']) ?>">
                                    <?php endif; ?>
                                    
                                    <div class="news-card-body">
                                        <h3 class="news-card-title"><?= h($translated_title) ?></h3>
                                        <p class="news-card-description"><?= h($translated_description) ?></p>
                                        
                                        <div class="news-meta">
                                            <div><i class="fas fa-building"></i> <strong>Source:</strong> <?= h($article['source']['name'] ?? 'Unknown') ?></div>
                                            <div><i class="fas fa-clock"></i> <strong>Published:</strong> <?= h($formatted_date) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="news-card-footer">
                                        <a href="<?= h($article['url']) ?>" class="read-more-btn" target="_blank">
                                            <i class="fas fa-external-link-alt"></i>
                                            Read Full Article
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-wrapper">
                                <div class="pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?state=<?= urlencode($selected_state) ?>&language=<?= $selected_language ?>&page=<?= $page - 1 ?>" 
                                           class="page-btn page-nav-btn">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1) {
                                        echo '<a href="?state=' . urlencode($selected_state) . '&language=' . $selected_language . '&page=1" class="page-btn">1</a>';
                                        if ($start_page > 2) {
                                            echo '<span class="page-btn disabled">...</span>';
                                        }
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        $active_class = $i == $page ? 'active' : '';
                                        echo '<a href="?state=' . urlencode($selected_state) . '&language=' . $selected_language . '&page=' . $i . '" class="page-btn ' . $active_class . '">' . $i . '</a>';
                                    }
                                    
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<span class="page-btn disabled">...</span>';
                                        }
                                        echo '<a href="?state=' . urlencode($selected_state) . '&language=' . $selected_language . '&page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?state=<?= urlencode($selected_state) ?>&language=<?= $selected_language ?>&page=<?= $page + 1 ?>" 
                                           class="page-btn page-nav-btn">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <h3><i class="fas fa-info-circle"></i> About This Portal</h3>
            <p>Powered by NewsAPI.org | API Key: <?= substr($api_key, 0, 5) ?>...<?= substr($api_key, -5) ?></p>
            <p><small>Disclaimer: This service uses NewsAPI and Google Translate APIs. For production use, please review their terms of service.</small></p>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <a href="#" class="scroll-top" id="scrollTop">
        <i class="fas fa-chevron-up"></i>
    </a>

    <script>
        // Scroll to top functionality
        const scrollTopBtn = document.getElementById('scrollTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTopBtn.classList.add('visible');
            } else {
                scrollTopBtn.classList.remove('visible');
            }
        });
        
        scrollTopBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Smooth scrolling for filter changes
        document.querySelectorAll('.filter-item').forEach(item => {
            item.addEventListener('click', (e) => {
                // Add loading state
                const loadingDiv = document.createElement('div');
                loadingDiv.innerHTML = '<div class="loading"></div> Loading...';
                loadingDiv.style.textAlign = 'center';
                loadingDiv.style.padding = '20px';
                
                const newsBody = document.querySelector('.news-body');
                if (newsBody) {
                    newsBody.innerHTML = '';
                    newsBody.appendChild(loadingDiv);
                }
            });
        });
    </script>
</body>
</html>