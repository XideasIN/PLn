<?php
/**
 * Dynamic Robots.txt Generator
 * LoanFlow Personal Loan Management System
 */

require_once 'includes/functions.php';
require_once 'includes/seo.php';

// Set plain text content type
header('Content-Type: text/plain; charset=utf-8');

// Generate and output robots.txt
echo SEOManager::generateRobotsTxt();
?>
