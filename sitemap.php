<?php
/**
 * Dynamic Sitemap Generator
 * LoanFlow Personal Loan Management System
 */

require_once 'includes/functions.php';
require_once 'includes/seo.php';

// Set XML content type
header('Content-Type: application/xml; charset=utf-8');

// Generate and output sitemap
echo SEOManager::generateSitemap();
?>
