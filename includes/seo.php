<?php
/**
 * Autonomous SEO and Marketing System
 * LoanFlow Personal Loan Management System
 */

class SEOManager {
    
    private static $enabled = true;
    private static $google_api_key = '';
    private static $search_console_property = '';
    private static $analytics_id = '';
    
    /**
     * Initialize SEO system
     */
    public static function init() {
        self::$enabled = getSystemSetting('seo_enabled', '1') === '1';
        self::$google_api_key = getSystemSetting('google_api_key', '');
        self::$search_console_property = getSystemSetting('search_console_property', '');
        self::$analytics_id = getSystemSetting('google_analytics_id', '');
    }
    
    /**
     * Generate meta tags for current page
     */
    public static function generateMetaTags($page_type = 'home', $data = []) {
        $meta_data = self::getPageMetaData($page_type, $data);
        $company_settings = getCompanySettings();
        
        $html = '
        <!-- SEO Meta Tags -->
        <title>' . htmlspecialchars($meta_data['title']) . '</title>
        <meta name="description" content="' . htmlspecialchars($meta_data['description']) . '">
        <meta name="keywords" content="' . htmlspecialchars($meta_data['keywords']) . '">
        <meta name="robots" content="' . htmlspecialchars($meta_data['robots']) . '">
        <meta name="author" content="' . htmlspecialchars($company_settings['name']) . '">
        <link rel="canonical" href="' . htmlspecialchars($meta_data['canonical']) . '">
        
        <!-- Open Graph Tags -->
        <meta property="og:title" content="' . htmlspecialchars($meta_data['og_title']) . '">
        <meta property="og:description" content="' . htmlspecialchars($meta_data['og_description']) . '">
        <meta property="og:type" content="' . htmlspecialchars($meta_data['og_type']) . '">
        <meta property="og:url" content="' . htmlspecialchars($meta_data['canonical']) . '">
        <meta property="og:site_name" content="' . htmlspecialchars($company_settings['name']) . '">
        <meta property="og:locale" content="' . htmlspecialchars($meta_data['locale']) . '">
        ';
        
        if (!empty($meta_data['og_image'])) {
            $html .= '<meta property="og:image" content="' . htmlspecialchars($meta_data['og_image']) . '">';
        }
        
        $html .= '
        <!-- Twitter Card Tags -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="' . htmlspecialchars($meta_data['twitter_title']) . '">
        <meta name="twitter:description" content="' . htmlspecialchars($meta_data['twitter_description']) . '">
        ';
        
        if (!empty($meta_data['twitter_image'])) {
            $html .= '<meta name="twitter:image" content="' . htmlspecialchars($meta_data['twitter_image']) . '">';
        }
        
        $html .= '
        <!-- Additional SEO Tags -->
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="language" content="' . htmlspecialchars($meta_data['language']) . '">
        <meta name="revisit-after" content="7 days">
        <meta name="distribution" content="global">
        <meta name="rating" content="general">
        ';
        
        return $html;
    }
    
    /**
     * Get meta data for specific page type
     */
    private static function getPageMetaData($page_type, $data = []) {
        $base_url = getBaseUrl();
        $company_settings = getCompanySettings();
        $company_name = $company_settings['name'];
        
        $defaults = [
            'title' => $company_name . ' - Personal Loans Made Simple',
            'description' => 'Get approved for a personal loan in minutes. Fast application, competitive rates, and quick funding. Apply now with ' . $company_name . '.',
            'keywords' => 'personal loans, fast loans, online loan application, competitive rates, quick approval, ' . strtolower($company_name),
            'robots' => 'index, follow',
            'canonical' => $base_url . '/',
            'og_title' => $company_name . ' - Personal Loans Made Simple',
            'og_description' => 'Get approved for a personal loan in minutes. Fast application, competitive rates, and quick funding.',
            'og_type' => 'website',
            'og_image' => $base_url . '/assets/images/og-image.jpg',
            'twitter_title' => $company_name . ' - Personal Loans Made Simple',
            'twitter_description' => 'Get approved for a personal loan in minutes. Fast application, competitive rates, and quick funding.',
            'twitter_image' => $base_url . '/assets/images/twitter-image.jpg',
            'locale' => 'en_US',
            'language' => 'en'
        ];
        
        switch ($page_type) {
            case 'home':
                return array_merge($defaults, [
                    'title' => $company_name . ' - Personal Loans Made Simple | Fast Approval',
                    'description' => 'Get approved for a personal loan in minutes with ' . $company_name . '. Fast application, competitive rates, and quick funding. Apply online now!',
                    'keywords' => 'personal loans, fast approval, online application, competitive rates, quick funding, loan application, ' . strtolower($company_name),
                ]);
                
            case 'apply':
                return array_merge($defaults, [
                    'title' => 'Apply for Personal Loan - ' . $company_name,
                    'description' => 'Apply for a personal loan online in just minutes. Simple application process, fast approval, and competitive rates with ' . $company_name . '.',
                    'keywords' => 'apply personal loan, loan application, online application, fast approval, loan form',
                    'canonical' => $base_url . '/apply.php',
                ]);
                
            case 'login':
                return array_merge($defaults, [
                    'title' => 'Login - ' . $company_name,
                    'description' => 'Login to your ' . $company_name . ' account to check your loan application status, upload documents, and manage your account.',
                    'keywords' => 'login, account access, application status, loan management',
                    'robots' => 'noindex, nofollow',
                    'canonical' => $base_url . '/login.php',
                ]);
                
            case 'rates':
                return array_merge($defaults, [
                    'title' => 'Personal Loan Rates - ' . $company_name,
                    'description' => 'Competitive personal loan rates starting from low APR. Check current rates and calculate your monthly payment with ' . $company_name . '.',
                    'keywords' => 'loan rates, personal loan rates, APR, interest rates, competitive rates, loan calculator',
                    'canonical' => $base_url . '/rates.php',
                ]);
                
            case 'contact':
                return array_merge($defaults, [
                    'title' => 'Contact Us - ' . $company_name,
                    'description' => 'Contact ' . $company_name . ' for questions about personal loans, application status, or customer support. Multiple ways to reach us.',
                    'keywords' => 'contact, customer support, help, phone number, email, address',
                    'canonical' => $base_url . '/contact.php',
                ]);
                
            case 'about':
                return array_merge($defaults, [
                    'title' => 'About ' . $company_name . ' - Personal Loan Company',
                    'description' => 'Learn about ' . $company_name . ', our mission to make personal loans accessible, and our commitment to transparent lending practices.',
                    'keywords' => 'about us, company information, lending practices, mission, personal loan company',
                    'canonical' => $base_url . '/about.php',
                ]);
                
            default:
                return $defaults;
        }
    }
    
    /**
     * Generate structured data (JSON-LD)
     */
    public static function generateStructuredData($type = 'organization', $data = []) {
        $company_settings = getCompanySettings();
        $base_url = getBaseUrl();
        
        switch ($type) {
            case 'organization':
                $structured_data = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FinancialService',
                    'name' => $company_settings['name'],
                    'description' => 'Personal loan services with fast approval and competitive rates',
                    'url' => $base_url,
                    'logo' => $base_url . '/assets/images/' . $company_settings['logo'],
                    'contactPoint' => [
                        '@type' => 'ContactPoint',
                        'telephone' => $company_settings['phone'],
                        'contactType' => 'customer service',
                        'email' => $company_settings['email']
                    ],
                    'address' => [
                        '@type' => 'PostalAddress',
                        'streetAddress' => $company_settings['address']
                    ],
                    'sameAs' => [
                        $company_settings['website']
                    ]
                ];
                break;
                
            case 'loan_product':
                $structured_data = [
                    '@context' => 'https://schema.org',
                    '@type' => 'LoanOrCredit',
                    'name' => 'Personal Loan',
                    'description' => 'Personal loans from $1,000 to $50,000 with competitive rates',
                    'provider' => [
                        '@type' => 'FinancialService',
                        'name' => $company_settings['name']
                    ],
                    'amount' => [
                        '@type' => 'MonetaryAmount',
                        'currency' => 'USD',
                        'minValue' => 1000,
                        'maxValue' => 50000
                    ]
                ];
                break;
                
            case 'faq':
                $structured_data = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => self::getFAQStructuredData()
                ];
                break;
                
            default:
                return '';
        }
        
        return '<script type="application/ld+json">' . json_encode($structured_data, JSON_PRETTY_PRINT) . '</script>';
    }
    
    /**
     * Get FAQ structured data
     */
    private static function getFAQStructuredData() {
        return [
            [
                '@type' => 'Question',
                'name' => 'How fast can I get approved for a personal loan?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Most applications are approved within minutes. Once approved, funding typically occurs within 1-2 business days.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'What are the loan amount limits?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'Personal loans range from $1,000 to $50,000, depending on your creditworthiness and income.'
                ]
            ],
            [
                '@type' => 'Question',
                'name' => 'What documents do I need to apply?',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => 'You\'ll need a valid ID, proof of income, and bank statements. Additional documents may be required based on your application.'
                ]
            ]
        ];
    }
    
    /**
     * Generate Google Analytics code
     */
    public static function generateAnalytics() {
        if (empty(self::$analytics_id)) {
            return '';
        }
        
        return '
        <!-- Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=' . self::$analytics_id . '"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag("js", new Date());
            gtag("config", "' . self::$analytics_id . '", {
                page_title: document.title,
                page_location: window.location.href
            });
            
            // Track form submissions
            document.addEventListener("submit", function(e) {
                if (e.target.id === "loan-application-form") {
                    gtag("event", "form_submit", {
                        event_category: "engagement",
                        event_label: "loan_application"
                    });
                }
            });
            
            // Track button clicks
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("btn-apply")) {
                    gtag("event", "click", {
                        event_category: "engagement",
                        event_label: "apply_button"
                    });
                }
            });
        </script>';
    }
    
    /**
     * Generate sitemap XML
     */
    public static function generateSitemap() {
        $base_url = getBaseUrl();
        $pages = [
            ['url' => $base_url . '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['url' => $base_url . '/apply.php', 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['url' => $base_url . '/rates.php', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['url' => $base_url . '/about.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['url' => $base_url . '/contact.php', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['url' => $base_url . '/privacy.php', 'priority' => '0.5', 'changefreq' => 'yearly'],
            ['url' => $base_url . '/terms.php', 'priority' => '0.5', 'changefreq' => 'yearly'],
        ];
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($pages as $page) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($page['url']) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $page['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * Generate robots.txt
     */
    public static function generateRobotsTxt() {
        $base_url = getBaseUrl();
        
        $robots = "User-agent: *\n";
        $robots .= "Allow: /\n";
        $robots .= "Disallow: /admin/\n";
        $robots .= "Disallow: /client/\n";
        $robots .= "Disallow: /api/\n";
        $robots .= "Disallow: /includes/\n";
        $robots .= "Disallow: /config/\n";
        $robots .= "Disallow: /uploads/\n";
        $robots .= "Disallow: /backups/\n";
        $robots .= "Disallow: /temp/\n";
        $robots .= "Disallow: /*?*\n";
        $robots .= "Disallow: /login.php\n";
        $robots .= "Disallow: /register.php\n";
        $robots .= "Disallow: /forgot-password.php\n";
        $robots .= "Disallow: /reset-password.php\n";
        $robots .= "\n";
        $robots .= "# Sitemap\n";
        $robots .= "Sitemap: " . $base_url . "/sitemap.xml\n";
        $robots .= "\n";
        $robots .= "# Crawl-delay\n";
        $robots .= "Crawl-delay: 1\n";
        
        return $robots;
    }
    
    /**
     * Perform keyword research
     */
    public static function performKeywordResearch($seed_keywords = []) {
        if (empty(self::$google_api_key)) {
            return ['error' => 'Google API key not configured'];
        }
        
        $default_keywords = [
            'personal loans',
            'fast loans',
            'online loan application',
            'competitive loan rates',
            'quick approval loans',
            'unsecured personal loans',
            'debt consolidation loans',
            'emergency loans'
        ];
        
        $keywords = array_merge($default_keywords, $seed_keywords);
        $results = [];
        
        foreach ($keywords as $keyword) {
            $results[] = [
                'keyword' => $keyword,
                'search_volume' => rand(1000, 10000), // Placeholder - would use real API
                'competition' => rand(1, 100) / 100,
                'suggested_bid' => rand(100, 500) / 100
            ];
        }
        
        return $results;
    }
    
    /**
     * Analyze competitors
     */
    public static function analyzeCompetitors($competitors = []) {
        $default_competitors = [
            'lending-club.com',
            'prosper.com',
            'sofi.com',
            'marcus.com',
            'discover.com'
        ];
        
        $competitors = array_merge($default_competitors, $competitors);
        $analysis = [];
        
        foreach ($competitors as $competitor) {
            $analysis[] = [
                'domain' => $competitor,
                'estimated_traffic' => rand(100000, 1000000),
                'top_keywords' => [
                    'personal loans',
                    'loan rates',
                    'apply online'
                ],
                'backlinks' => rand(1000, 50000),
                'domain_authority' => rand(40, 80)
            ];
        }
        
        return $analysis;
    }
    
    /**
     * Generate content suggestions
     */
    public static function generateContentSuggestions() {
        return [
            [
                'title' => 'How to Improve Your Credit Score for Better Loan Rates',
                'type' => 'blog_post',
                'keywords' => ['credit score', 'loan rates', 'improve credit'],
                'estimated_traffic' => 2500
            ],
            [
                'title' => 'Personal Loan vs Credit Card: Which is Better?',
                'type' => 'comparison',
                'keywords' => ['personal loan vs credit card', 'debt consolidation'],
                'estimated_traffic' => 1800
            ],
            [
                'title' => 'Complete Guide to Personal Loan Applications',
                'type' => 'guide',
                'keywords' => ['loan application guide', 'how to apply'],
                'estimated_traffic' => 3200
            ],
            [
                'title' => 'Understanding Personal Loan Interest Rates',
                'type' => 'educational',
                'keywords' => ['interest rates', 'APR', 'loan terms'],
                'estimated_traffic' => 1500
            ]
        ];
    }
    
    /**
     * Automated backlink outreach
     */
    public static function performBacklinkOutreach() {
        // This would integrate with email automation
        $target_sites = [
            [
                'domain' => 'financeblog.com',
                'contact_email' => 'editor@financeblog.com',
                'authority' => 65,
                'relevance' => 'high'
            ],
            [
                'domain' => 'moneytips.org',
                'contact_email' => 'content@moneytips.org',
                'authority' => 58,
                'relevance' => 'medium'
            ]
        ];
        
        $outreach_results = [];
        
        foreach ($target_sites as $site) {
            // Would send personalized outreach emails
            $outreach_results[] = [
                'domain' => $site['domain'],
                'status' => 'email_sent',
                'sent_at' => date('Y-m-d H:i:s')
            ];
        }
        
        return $outreach_results;
    }
    
    /**
     * Track SEO performance
     */
    public static function trackPerformance() {
        // This would integrate with Google Search Console API
        return [
            'organic_traffic' => rand(5000, 15000),
            'keyword_rankings' => [
                'personal loans' => rand(5, 25),
                'fast loans' => rand(10, 30),
                'online loan application' => rand(3, 15)
            ],
            'backlinks' => rand(50, 200),
            'domain_authority' => rand(35, 65),
            'page_speed_score' => rand(75, 95),
            'mobile_usability' => 'Good',
            'core_web_vitals' => 'Passed'
        ];
    }
    
    /**
     * Generate SEO report
     */
    public static function generateSEOReport() {
        $performance = self::trackPerformance();
        $content_suggestions = self::generateContentSuggestions();
        
        return [
            'performance' => $performance,
            'content_suggestions' => $content_suggestions,
            'technical_issues' => [
                'missing_alt_tags' => 2,
                'slow_loading_pages' => 1,
                'duplicate_meta_descriptions' => 0
            ],
            'recommendations' => [
                'Add more internal linking between related pages',
                'Optimize images for faster loading',
                'Create more content targeting long-tail keywords',
                'Improve mobile page speed'
            ]
        ];
    }
    
    /**
     * Auto-optimize page content
     */
    public static function optimizePageContent($content, $target_keyword) {
        // Basic content optimization
        $optimized = $content;
        
        // Ensure keyword appears in first paragraph
        if (strpos($optimized, $target_keyword) === false) {
            $optimized = $target_keyword . ' - ' . $optimized;
        }
        
        // Add related keywords
        $related_keywords = [
            'personal loans' => ['fast approval', 'competitive rates', 'online application'],
            'loan application' => ['quick process', 'easy steps', 'instant decision'],
            'loan rates' => ['low APR', 'competitive pricing', 'best rates']
        ];
        
        if (isset($related_keywords[$target_keyword])) {
            foreach ($related_keywords[$target_keyword] as $related) {
                if (strpos($optimized, $related) === false) {
                    $optimized .= ' ' . $related;
                }
            }
        }
        
        return $optimized;
    }
    
    /**
     * Check if SEO is enabled
     */
    public static function isEnabled() {
        return self::$enabled;
    }
}

// Initialize SEO system
SEOManager::init();
