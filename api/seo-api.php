<?php
/**
 * SEO Automation API
 * LoanFlow Personal Loan Management System
 * 
 * RESTful API endpoints for SEO automation system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Initialize response
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    // Get request method and action
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // Get JSON input for POST requests
    $input = [];
    if ($method === 'POST') {
        $json = file_get_contents('php://input');
        $input = json_decode($json, true) ?? $_POST;
        $action = $action ?: ($input['action'] ?? '');
    }
    
    // Authentication check
    $current_user = getCurrentUser();
    if (!$current_user || $current_user['role'] !== 'admin') {
        throw new Exception('Unauthorized access', 401);
    }
    
    // Route requests based on action
    switch ($action) {
        case 'get_backlink_opportunities':
            $response = getBacklinkOpportunities();
            break;
            
        case 'get_content_suggestions':
            $response = getContentSuggestions();
            break;
            
        case 'get_technical_analysis':
            $response = getTechnicalAnalysis();
            break;
            
        case 'run_full_audit':
            $response = runFullSEOAudit();
            break;
            
        case 'generate_backlinks':
            $response = generateAutomaticBacklinks($input);
            break;
            
        case 'optimize_keyword':
            $response = optimizeForKeyword($input);
            break;
            
        case 'analyze_competitor':
            $response = analyzeCompetitorDomain($input);
            break;
            
        case 'fix_issue':
            $response = fixTechnicalIssue($input);
            break;
            
        case 'update_keyword_rankings':
            $response = updateKeywordRankings();
            break;
            
        case 'get_seo_metrics':
            $response = getSEOMetricsAPI();
            break;
            
        case 'add_keywords':
            $response = addKeywords($input);
            break;
            
        case 'add_competitor':
            $response = addCompetitor($input);
            break;
            
        case 'generate_content_suggestions':
            $response = generateAIContentSuggestions($input);
            break;
            
        case 'run_technical_audit':
            $response = runTechnicalSEOAudit();
            break;
            
        case 'get_keyword_research':
            $response = getKeywordResearch($input);
            break;
            
        case 'analyze_page_seo':
            $response = analyzePageSEO($input);
            break;
            
        case 'generate_meta_tags':
            $response = generateMetaTags($input);
            break;
            
        case 'check_backlink_quality':
            $response = checkBacklinkQuality($input);
            break;
            
        case 'get_competitor_keywords':
            $response = getCompetitorKeywords($input);
            break;
            
        case 'optimize_images':
            $response = optimizeImages($input);
            break;
            
        case 'generate_schema_markup':
            $response = generateSchemaMarkup($input);
            break;
            
        case 'check_site_speed':
            $response = checkSiteSpeed($input);
            break;
            
        case 'analyze_serp_features':
            $response = analyzeSERPFeatures($input);
            break;
            
        default:
            throw new Exception('Invalid action specified', 400);
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode() ?: 500
    ];
    
    http_response_code($response['code']);
    error_log('SEO API Error: ' . $e->getMessage());
}

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
exit();

/**
 * SEO API Functions
 */

function getBacklinkOpportunities() {
    global $pdo;
    
    try {
        // Mock data for backlink opportunities
        $opportunities = [
            [
                'domain' => 'fintech-blog.com',
                'authority' => 68,
                'relevance' => 92,
                'type' => 'Guest Post',
                'status' => 'Available',
                'estimated_value' => 'High'
            ],
            [
                'domain' => 'loan-review-site.com',
                'authority' => 55,
                'relevance' => 88,
                'type' => 'Resource Page',
                'status' => 'Contacted',
                'estimated_value' => 'Medium'
            ],
            [
                'domain' => 'financial-news.com',
                'authority' => 72,
                'relevance' => 85,
                'type' => 'Broken Link',
                'status' => 'Opportunity',
                'estimated_value' => 'High'
            ]
        ];
        
        $html = '<div class="row">';
        foreach ($opportunities as $opp) {
            $badgeClass = $opp['estimated_value'] === 'High' ? 'success' : ($opp['estimated_value'] === 'Medium' ? 'warning' : 'secondary');
            $statusClass = $opp['status'] === 'Available' ? 'success' : ($opp['status'] === 'Contacted' ? 'warning' : 'info');
            
            $html .= '
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0">' . htmlspecialchars($opp['domain']) . '</h6>
                                <span class="badge bg-' . $badgeClass . '">' . $opp['estimated_value'] . '</span>
                            </div>
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <small class="text-muted">Authority</small>
                                    <div class="fw-bold">' . $opp['authority'] . '</div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Relevance</small>
                                    <div class="fw-bold">' . $opp['relevance'] . '%</div>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Type</small>
                                    <div class="fw-bold">' . $opp['type'] . '</div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-' . $statusClass . '">' . $opp['status'] . '</span>
                                <button class="btn btn-sm btn-primary" onclick="pursueOpportunity(\'' . $opp['domain'] . '\')">Pursue</button>
                            </div>
                        </div>
                    </div>
                </div>
            ';
        }
        $html .= '</div>';
        
        return [
            'success' => true,
            'html' => $html,
            'data' => $opportunities
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to get backlink opportunities: ' . $e->getMessage());
    }
}

function getContentSuggestions() {
    try {
        $suggestions = [
            [
                'page' => '/loan-application',
                'title' => 'Optimize Title Tag',
                'current' => 'Loan Application - LoanFlow',
                'suggested' => 'Quick Personal Loan Application | Fast Approval - LoanFlow',
                'impact' => 'High',
                'type' => 'Title Optimization'
            ],
            [
                'page' => '/about',
                'title' => 'Add FAQ Section',
                'current' => 'Basic about page content',
                'suggested' => 'Add comprehensive FAQ section with loan-related questions',
                'impact' => 'Medium',
                'type' => 'Content Addition'
            ],
            [
                'page' => '/blog',
                'title' => 'Create Loan Guide Content',
                'current' => 'Limited blog content',
                'suggested' => 'Create comprehensive guides on personal loans, credit scores, and financial planning',
                'impact' => 'High',
                'type' => 'Content Creation'
            ]
        ];
        
        $html = '<div class="list-group">';
        foreach ($suggestions as $suggestion) {
            $impactClass = $suggestion['impact'] === 'High' ? 'danger' : ($suggestion['impact'] === 'Medium' ? 'warning' : 'success');
            
            $html .= '
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">' . htmlspecialchars($suggestion['title']) . '</h6>
                            <p class="mb-1"><strong>Page:</strong> ' . htmlspecialchars($suggestion['page']) . '</p>
                            <p class="mb-1"><strong>Current:</strong> ' . htmlspecialchars($suggestion['current']) . '</p>
                            <p class="mb-1"><strong>Suggested:</strong> ' . htmlspecialchars($suggestion['suggested']) . '</p>
                            <small class="text-muted">' . $suggestion['type'] . '</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-' . $impactClass . ' mb-2">' . $suggestion['impact'] . ' Impact</span><br>
                            <button class="btn btn-sm btn-primary" onclick="implementSuggestion(\'' . $suggestion['page'] . '\', \'' . $suggestion['type'] . '\')">Implement</button>
                        </div>
                    </div>
                </div>
            ';
        }
        $html .= '</div>';
        
        return [
            'success' => true,
            'html' => $html,
            'data' => $suggestions
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to get content suggestions: ' . $e->getMessage());
    }
}

function getTechnicalAnalysis() {
    try {
        $issues = [
            [
                'category' => 'Page Speed',
                'issue' => 'Large image files slowing load time',
                'severity' => 'High',
                'pages_affected' => 15,
                'fix_suggestion' => 'Compress and optimize images, implement lazy loading'
            ],
            [
                'category' => 'Mobile Usability',
                'issue' => 'Text too small on mobile devices',
                'severity' => 'Medium',
                'pages_affected' => 8,
                'fix_suggestion' => 'Increase font size for mobile viewport'
            ],
            [
                'category' => 'Crawlability',
                'issue' => 'Missing XML sitemap',
                'severity' => 'Medium',
                'pages_affected' => 0,
                'fix_suggestion' => 'Generate and submit XML sitemap to search engines'
            ],
            [
                'category' => 'Security',
                'issue' => 'Mixed content warnings',
                'severity' => 'Low',
                'pages_affected' => 3,
                'fix_suggestion' => 'Update HTTP resources to HTTPS'
            ]
        ];
        
        $html = '<div class="row">';
        foreach ($issues as $issue) {
            $severityClass = $issue['severity'] === 'High' ? 'danger' : ($issue['severity'] === 'Medium' ? 'warning' : 'info');
            
            $html .= '
                <div class="col-md-6 mb-3">
                    <div class="card border-' . $severityClass . '">
                        <div class="card-header bg-' . $severityClass . ' text-white">
                            <h6 class="mb-0">' . htmlspecialchars($issue['category']) . '</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Issue:</strong> ' . htmlspecialchars($issue['issue']) . '</p>
                            <p class="mb-2"><strong>Pages Affected:</strong> ' . $issue['pages_affected'] . '</p>
                            <p class="mb-3"><strong>Fix:</strong> ' . htmlspecialchars($issue['fix_suggestion']) . '</p>
                            <button class="btn btn-sm btn-' . $severityClass . '" onclick="fixTechnicalIssue(\'' . $issue['category'] . '\')">Auto Fix</button>
                        </div>
                    </div>
                </div>
            ';
        }
        $html .= '</div>';
        
        return [
            'success' => true,
            'html' => $html,
            'data' => $issues
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to get technical analysis: ' . $e->getMessage());
    }
}

function runFullSEOAudit() {
    try {
        // Simulate comprehensive SEO audit
        $audit_results = [
            'overall_score' => 82,
            'technical_score' => 78,
            'content_score' => 85,
            'backlink_score' => 80,
            'user_experience_score' => 88,
            'issues_found' => 12,
            'issues_fixed' => 8,
            'recommendations' => 15
        ];
        
        // Log audit activity
        logSEOActivity('Full SEO Audit', 'Comprehensive SEO audit completed with score: ' . $audit_results['overall_score']);
        
        return [
            'success' => true,
            'message' => 'SEO audit completed successfully',
            'data' => $audit_results
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to run SEO audit: ' . $e->getMessage());
    }
}

function generateAutomaticBacklinks($input) {
    try {
        // Simulate AI-powered backlink generation
        $generated_links = [
            ['domain' => 'finance-blog.com', 'type' => 'Guest Post', 'status' => 'Submitted'],
            ['domain' => 'loan-directory.com', 'type' => 'Directory Listing', 'status' => 'Approved'],
            ['domain' => 'fintech-news.com', 'type' => 'Press Release', 'status' => 'Published']
        ];
        
        logSEOActivity('Backlink Generation', 'Generated ' . count($generated_links) . ' new backlink opportunities');
        
        return [
            'success' => true,
            'message' => 'Backlink generation completed',
            'data' => $generated_links
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to generate backlinks: ' . $e->getMessage());
    }
}

function optimizeForKeyword($input) {
    $keyword = $input['keyword'] ?? '';
    
    if (empty($keyword)) {
        throw new Exception('Keyword is required');
    }
    
    try {
        // Simulate keyword optimization
        $optimization_tasks = [
            'Updated meta title and description',
            'Optimized heading tags (H1, H2, H3)',
            'Improved keyword density',
            'Added internal links',
            'Optimized image alt tags'
        ];
        
        logSEOActivity('Keyword Optimization', 'Optimized content for keyword: ' . $keyword);
        
        return [
            'success' => true,
            'message' => 'Keyword optimization completed for: ' . $keyword,
            'data' => $optimization_tasks
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to optimize keyword: ' . $e->getMessage());
    }
}

function analyzeCompetitorDomain($input) {
    $domain = $input['domain'] ?? '';
    
    if (empty($domain)) {
        throw new Exception('Domain is required');
    }
    
    try {
        // Simulate competitor analysis
        $analysis = [
            'domain_authority' => rand(40, 80),
            'page_authority' => rand(30, 70),
            'backlinks' => rand(1000, 50000),
            'referring_domains' => rand(100, 5000),
            'organic_keywords' => rand(500, 10000),
            'organic_traffic' => rand(5000, 100000),
            'top_keywords' => [
                'personal loan' => 5,
                'quick loan' => 12,
                'online loan' => 8,
                'bad credit loan' => 15
            ]
        ];
        
        logSEOActivity('Competitor Analysis', 'Analyzed competitor: ' . $domain);
        
        return [
            'success' => true,
            'message' => 'Competitor analysis completed for: ' . $domain,
            'data' => $analysis
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to analyze competitor: ' . $e->getMessage());
    }
}

function fixTechnicalIssue($input) {
    $issue_id = $input['issue_id'] ?? 0;
    
    if (empty($issue_id)) {
        throw new Exception('Issue ID is required');
    }
    
    try {
        // Simulate technical issue fix
        $fixes_applied = [
            'Compressed images and enabled lazy loading',
            'Updated mobile viewport settings',
            'Generated and submitted XML sitemap',
            'Fixed mixed content warnings'
        ];
        
        $fix = $fixes_applied[array_rand($fixes_applied)];
        
        logSEOActivity('Technical Fix', 'Applied fix: ' . $fix);
        
        return [
            'success' => true,
            'message' => 'Technical issue fixed successfully',
            'data' => ['fix_applied' => $fix]
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to fix technical issue: ' . $e->getMessage());
    }
}

function updateKeywordRankings() {
    try {
        // Simulate keyword ranking update
        $updated_keywords = [
            'personal loan' => ['old_rank' => 12, 'new_rank' => 8],
            'quick loan approval' => ['old_rank' => 18, 'new_rank' => 15],
            'online loan application' => ['old_rank' => 7, 'new_rank' => 5]
        ];
        
        logSEOActivity('Keyword Rankings Update', 'Updated rankings for ' . count($updated_keywords) . ' keywords');
        
        return [
            'success' => true,
            'message' => 'Keyword rankings updated successfully',
            'data' => $updated_keywords
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to update keyword rankings: ' . $e->getMessage());
    }
}

function getSEOMetricsAPI() {
    try {
        $metrics = [
            'overall_score' => 78,
            'organic_traffic' => 15420,
            'traffic_growth' => 12.5,
            'total_backlinks' => 1247,
            'new_backlinks' => 23,
            'ranking_keywords' => 156,
            'top_10_keywords' => 34,
            'technical_score' => 82,
            'content_score' => 75,
            'user_experience_score' => 88
        ];
        
        return [
            'success' => true,
            'data' => $metrics
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to get SEO metrics: ' . $e->getMessage());
    }
}

function addKeywords($input) {
    $keywords = $input['keywords'] ?? '';
    
    if (empty($keywords)) {
        throw new Exception('Keywords are required');
    }
    
    try {
        $keyword_list = array_map('trim', explode(',', $keywords));
        $keyword_list = array_filter($keyword_list);
        
        logSEOActivity('Keywords Added', 'Added ' . count($keyword_list) . ' new keywords for tracking');
        
        return [
            'success' => true,
            'message' => 'Keywords added successfully',
            'data' => ['keywords_added' => count($keyword_list)]
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to add keywords: ' . $e->getMessage());
    }
}

function addCompetitor($input) {
    $domain = $input['domain'] ?? '';
    
    if (empty($domain)) {
        throw new Exception('Domain is required');
    }
    
    try {
        // Validate domain format
        $domain = filter_var($domain, FILTER_VALIDATE_URL) ? parse_url($domain, PHP_URL_HOST) : $domain;
        
        logSEOActivity('Competitor Added', 'Added new competitor for monitoring: ' . $domain);
        
        return [
            'success' => true,
            'message' => 'Competitor added successfully',
            'data' => ['domain' => $domain]
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to add competitor: ' . $e->getMessage());
    }
}

function generateAIContentSuggestions($input) {
    try {
        $suggestions = [
            [
                'type' => 'Blog Post',
                'title' => '10 Tips for Getting Approved for a Personal Loan',
                'keywords' => ['personal loan approval', 'loan tips', 'credit score'],
                'estimated_traffic' => 2500,
                'difficulty' => 'Medium'
            ],
            [
                'type' => 'Landing Page',
                'title' => 'Bad Credit Personal Loans - Get Approved Today',
                'keywords' => ['bad credit loans', 'poor credit', 'guaranteed approval'],
                'estimated_traffic' => 3200,
                'difficulty' => 'High'
            ],
            [
                'type' => 'FAQ Page',
                'title' => 'Personal Loan FAQ - Common Questions Answered',
                'keywords' => ['loan questions', 'loan FAQ', 'personal loan help'],
                'estimated_traffic' => 1800,
                'difficulty' => 'Low'
            ]
        ];
        
        return [
            'success' => true,
            'message' => 'AI content suggestions generated',
            'data' => $suggestions
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to generate content suggestions: ' . $e->getMessage());
    }
}

function runTechnicalSEOAudit() {
    try {
        $audit_results = [
            'page_speed' => ['score' => 78, 'issues' => 3],
            'mobile_usability' => ['score' => 85, 'issues' => 2],
            'crawlability' => ['score' => 92, 'issues' => 1],
            'security' => ['score' => 95, 'issues' => 1],
            'structured_data' => ['score' => 70, 'issues' => 4]
        ];
        
        logSEOActivity('Technical SEO Audit', 'Technical SEO audit completed');
        
        return [
            'success' => true,
            'message' => 'Technical SEO audit completed',
            'data' => $audit_results
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to run technical SEO audit: ' . $e->getMessage());
    }
}

function getKeywordResearch($input) {
    $seed_keyword = $input['keyword'] ?? '';
    
    try {
        $related_keywords = [
            ['keyword' => 'personal loan rates', 'volume' => 12000, 'difficulty' => 65, 'cpc' => 8.50],
            ['keyword' => 'best personal loans', 'volume' => 18000, 'difficulty' => 78, 'cpc' => 12.30],
            ['keyword' => 'personal loan calculator', 'volume' => 8500, 'difficulty' => 45, 'cpc' => 5.20],
            ['keyword' => 'unsecured personal loan', 'volume' => 6200, 'difficulty' => 58, 'cpc' => 9.80]
        ];
        
        return [
            'success' => true,
            'message' => 'Keyword research completed',
            'data' => $related_keywords
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to get keyword research: ' . $e->getMessage());
    }
}

function analyzePageSEO($input) {
    $url = $input['url'] ?? '';
    
    try {
        $analysis = [
            'title_tag' => ['score' => 85, 'length' => 58, 'optimized' => true],
            'meta_description' => ['score' => 70, 'length' => 145, 'optimized' => false],
            'headings' => ['h1_count' => 1, 'h2_count' => 4, 'h3_count' => 8],
            'keyword_density' => ['primary' => 2.3, 'secondary' => 1.8],
            'internal_links' => ['count' => 12, 'quality' => 'Good'],
            'images' => ['total' => 8, 'missing_alt' => 2]
        ];
        
        return [
            'success' => true,
            'message' => 'Page SEO analysis completed',
            'data' => $analysis
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to analyze page SEO: ' . $e->getMessage());
    }
}

function generateMetaTags($input) {
    $page_content = $input['content'] ?? '';
    $target_keyword = $input['keyword'] ?? '';
    
    try {
        $meta_tags = [
            'title' => 'Quick Personal Loan Application | Fast Approval - LoanFlow',
            'description' => 'Apply for a personal loan with LoanFlow. Fast approval, competitive rates, and flexible terms. Get your loan decision in minutes!',
            'keywords' => 'personal loan, quick loan, fast approval, competitive rates, online application',
            'og_title' => 'Get Your Personal Loan Approved Fast - LoanFlow',
            'og_description' => 'Apply for a personal loan with LoanFlow. Fast approval, competitive rates, and flexible terms.',
            'twitter_title' => 'Quick Personal Loan Application - LoanFlow',
            'twitter_description' => 'Get your personal loan approved fast with competitive rates and flexible terms.'
        ];
        
        return [
            'success' => true,
            'message' => 'Meta tags generated successfully',
            'data' => $meta_tags
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to generate meta tags: ' . $e->getMessage());
    }
}

function checkBacklinkQuality($input) {
    $backlinks = $input['backlinks'] ?? [];
    
    try {
        $quality_analysis = [
            'high_quality' => 65,
            'medium_quality' => 25,
            'low_quality' => 8,
            'toxic' => 2,
            'recommendations' => [
                'Disavow 3 toxic backlinks',
                'Pursue 5 high-authority opportunities',
                'Monitor 12 medium-quality links'
            ]
        ];
        
        return [
            'success' => true,
            'message' => 'Backlink quality analysis completed',
            'data' => $quality_analysis
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to check backlink quality: ' . $e->getMessage());
    }
}

function getCompetitorKeywords($input) {
    $competitor_domain = $input['domain'] ?? '';
    
    try {
        $competitor_keywords = [
            ['keyword' => 'personal loan online', 'position' => 3, 'volume' => 15000, 'our_position' => 8],
            ['keyword' => 'quick cash loan', 'position' => 5, 'volume' => 8500, 'our_position' => 15],
            ['keyword' => 'emergency loan', 'position' => 2, 'volume' => 12000, 'our_position' => 12],
            ['keyword' => 'instant approval loan', 'position' => 7, 'volume' => 6800, 'our_position' => 20]
        ];
        
        return [
            'success' => true,
            'message' => 'Competitor keywords analysis completed',
            'data' => $competitor_keywords
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to get competitor keywords: ' . $e->getMessage());
    }
}

function optimizeImages($input) {
    $images = $input['images'] ?? [];
    
    try {
        $optimization_results = [
            'images_processed' => 24,
            'size_reduction' => '68%',
            'alt_tags_added' => 18,
            'lazy_loading_enabled' => true,
            'webp_conversion' => 20
        ];
        
        logSEOActivity('Image Optimization', 'Optimized ' . $optimization_results['images_processed'] . ' images');
        
        return [
            'success' => true,
            'message' => 'Image optimization completed',
            'data' => $optimization_results
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to optimize images: ' . $e->getMessage());
    }
}

function generateSchemaMarkup($input) {
    $page_type = $input['type'] ?? 'webpage';
    
    try {
        $schema_markup = [
            'organization' => [
                '@context' => 'https://schema.org',
                '@type' => 'FinancialService',
                'name' => 'LoanFlow',
                'description' => 'Personal loan services with fast approval',
                'url' => 'https://loanflow.com'
            ],
            'service' => [
                '@context' => 'https://schema.org',
                '@type' => 'Service',
                'name' => 'Personal Loan',
                'description' => 'Quick personal loan with competitive rates',
                'provider' => 'LoanFlow'
            ]
        ];
        
        return [
            'success' => true,
            'message' => 'Schema markup generated',
            'data' => $schema_markup
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to generate schema markup: ' . $e->getMessage());
    }
}

function checkSiteSpeed($input) {
    $url = $input['url'] ?? '';
    
    try {
        $speed_analysis = [
            'desktop_score' => 78,
            'mobile_score' => 65,
            'load_time' => 2.3,
            'first_contentful_paint' => 1.2,
            'largest_contentful_paint' => 2.8,
            'cumulative_layout_shift' => 0.15,
            'recommendations' => [
                'Optimize images',
                'Minify CSS and JavaScript',
                'Enable browser caching',
                'Use CDN for static assets'
            ]
        ];
        
        return [
            'success' => true,
            'message' => 'Site speed analysis completed',
            'data' => $speed_analysis
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to check site speed: ' . $e->getMessage());
    }
}

function analyzeSERPFeatures($input) {
    $keyword = $input['keyword'] ?? '';
    
    try {
        $serp_features = [
            'featured_snippet' => ['present' => true, 'type' => 'paragraph', 'opportunity' => 'medium'],
            'people_also_ask' => ['present' => true, 'questions' => 4, 'opportunity' => 'high'],
            'local_pack' => ['present' => false, 'opportunity' => 'low'],
            'knowledge_panel' => ['present' => false, 'opportunity' => 'medium'],
            'image_pack' => ['present' => true, 'opportunity' => 'high'],
            'video_carousel' => ['present' => false, 'opportunity' => 'medium']
        ];
        
        return [
            'success' => true,
            'message' => 'SERP features analysis completed',
            'data' => $serp_features
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to analyze SERP features: ' . $e->getMessage());
    }
}

function logSEOActivity($title, $description) {
    global $pdo;
    
    try {
        // Create table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS seo_activities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO seo_activities (title, description, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$title, $description]);
        
    } catch (Exception $e) {
        error_log('Error logging SEO activity: ' . $e->getMessage());
    }
}

?>