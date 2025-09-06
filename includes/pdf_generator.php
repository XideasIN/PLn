<?php
/**
 * PDF Generator Class
 * LoanFlow Personal Loan Management System
 * 
 * Generates PDF documents from HTML content with personalization
 */

require_once 'config.php';

class PDFGenerator {
    private $mpdf;
    private $default_config;
    
    public function __construct() {
        // Check if mPDF is available
        if (!class_exists('Mpdf\Mpdf')) {
            // Fallback to basic HTML-to-PDF conversion
            $this->mpdf = null;
        } else {
            $this->mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 16,
                'margin_bottom' => 16,
                'margin_header' => 9,
                'margin_footer' => 9
            ]);
        }
        
        $this->default_config = [
            'company_name' => 'LoanFlow Financial Services',
            'company_address' => '123 Financial District, Suite 456, New York, NY 10001',
            'company_phone' => '(555) 123-4567',
            'company_email' => 'info@loanflow.com',
            'company_website' => 'www.loanflow.com'
        ];
    }
    
    /**
     * Generate PDF from document data
     */
    public function generateDocumentPDF($document, $user_info) {
        if ($this->mpdf) {
            return $this->generateWithMPDF($document, $user_info);
        } else {
            return $this->generateBasicPDF($document, $user_info);
        }
    }
    
    /**
     * Generate PDF using mPDF library
     */
    private function generateWithMPDF($document, $user_info) {
        // Personalize content
        $personalized_content = $this->personalizeContent($document['content'], $user_info);
        
        // Convert to HTML
        $html_content = $this->convertToHTML($personalized_content);
        
        // Set document properties
        $this->mpdf->SetTitle($document['title']);
        $this->mpdf->SetAuthor($this->default_config['company_name']);
        $this->mpdf->SetCreator('LoanFlow Document System');
        $this->mpdf->SetSubject($document['title']);
        
        // Set header and footer
        $this->mpdf->SetHTMLHeader($this->generateHeader($document, $user_info));
        $this->mpdf->SetHTMLFooter($this->generateFooter());
        
        // Add CSS styling
        $this->mpdf->WriteHTML($this->getDocumentCSS(), 1);
        
        // Write content
        $this->mpdf->WriteHTML($html_content, 2);
        
        return $this->mpdf->Output('', 'S');
    }
    
    /**
     * Generate basic PDF without mPDF (fallback)
     */
    private function generateBasicPDF($document, $user_info) {
        // This is a basic fallback - in production, you'd want to use a proper PDF library
        $personalized_content = $this->personalizeContent($document['content'], $user_info);
        $html_content = $this->convertToHTML($personalized_content);
        
        // Create a simple HTML document
        $full_html = $this->generateFullHTML($document, $user_info, $html_content);
        
        // For demonstration, we'll return the HTML as "PDF"
        // In production, you'd use a service like wkhtmltopdf or similar
        return $this->convertHTMLToPDF($full_html);
    }
    
    /**
     * Personalize document content with user data
     */
    private function personalizeContent($content, $user_info) {
        // Get loan application data
        $loan_data = $this->getLoanApplicationData($user_info['id']);
        
        // Define replacement variables
        $variables = [
            // Personal Information
            '{{client_name}}' => $this->getFullName($user_info),
            '{{first_name}}' => $user_info['first_name'] ?? '',
            '{{last_name}}' => $user_info['last_name'] ?? '',
            '{{email}}' => $user_info['email'] ?? '',
            '{{phone}}' => $user_info['phone'] ?? '',
            '{{address}}' => $this->formatAddress($user_info),
            '{{date_of_birth}}' => $this->formatDate($user_info['date_of_birth'] ?? ''),
            '{{ssn}}' => $this->formatSSN($user_info['ssn'] ?? ''),
            
            // Employment Information
            '{{employer}}' => $user_info['employer'] ?? '',
            '{{job_title}}' => $user_info['job_title'] ?? '',
            '{{annual_income}}' => $this->formatCurrency($user_info['annual_income'] ?? 0),
            '{{employment_length}}' => $user_info['employment_length'] ?? '',
            
            // Loan Information
            '{{loan_amount}}' => $this->formatCurrency($loan_data['loan_amount'] ?? 0),
            '{{loan_term}}' => ($loan_data['loan_term'] ?? 0) . ' months',
            '{{interest_rate}}' => ($loan_data['interest_rate'] ?? 0) . '%',
            '{{monthly_payment}}' => $this->formatCurrency($loan_data['monthly_payment'] ?? 0),
            '{{application_id}}' => $loan_data['application_id'] ?? 'N/A',
            '{{reference_number}}' => $loan_data['reference_number'] ?? $this->generateReferenceNumber($user_info['id']),
            '{{loan_purpose}}' => $loan_data['loan_purpose'] ?? '',
            '{{application_date}}' => $this->formatDate($loan_data['created_at'] ?? ''),
            
            // Company Information
            '{{company_name}}' => $this->default_config['company_name'],
            '{{company_address}}' => $this->default_config['company_address'],
            '{{company_phone}}' => $this->default_config['company_phone'],
            '{{company_email}}' => $this->default_config['company_email'],
            '{{company_website}}' => $this->default_config['company_website'],
            
            // System Information
            '{{current_date}}' => date('F j, Y'),
            '{{current_time}}' => date('g:i A'),
            '{{current_year}}' => date('Y'),
            '{{document_date}}' => date('F j, Y'),
            '{{expiry_date}}' => date('F j, Y', strtotime('+1 year')),
            
            // Legal Information
            '{{state}}' => $user_info['state'] ?? 'New York',
            '{{governing_law}}' => $user_info['state'] ?? 'New York',
        ];
        
        // Apply replacements
        $personalized_content = $content;
        foreach ($variables as $variable => $value) {
            $personalized_content = str_replace($variable, $value, $personalized_content);
        }
        
        return $personalized_content;
    }
    
    /**
     * Convert markdown-like content to HTML
     */
    private function convertToHTML($content) {
        $html = $content;
        
        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        
        // Lists
        $html = preg_replace('/^\* (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $html);
        
        // Wrap consecutive list items in ul tags
        $html = preg_replace('/((<li>.*?<\/li>\s*)+)/s', '<ul>$1</ul>', $html);
        
        // Paragraphs
        $paragraphs = explode("\n\n", $html);
        $html_paragraphs = [];
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // Don't wrap headers and lists in paragraphs
                if (!preg_match('/^<(h[1-6]|ul|ol|li)/', $paragraph)) {
                    $paragraph = '<p>' . nl2br($paragraph) . '</p>';
                } else {
                    $paragraph = nl2br($paragraph);
                }
                $html_paragraphs[] = $paragraph;
            }
        }
        
        return implode("\n\n", $html_paragraphs);
    }
    
    /**
     * Generate PDF header
     */
    private function generateHeader($document, $user_info) {
        return '
        <table width="100%" style="border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 20px;">
            <tr>
                <td width="70%">
                    <h2 style="margin: 0; color: #2c3e50;">' . $this->default_config['company_name'] . '</h2>
                    <p style="margin: 5px 0; font-size: 12px; color: #666;">' . $this->default_config['company_address'] . '</p>
                </td>
                <td width="30%" style="text-align: right;">
                    <p style="margin: 0; font-size: 12px;"><strong>Document:</strong> ' . htmlspecialchars($document['title']) . '</p>
                    <p style="margin: 5px 0; font-size: 12px;"><strong>Date:</strong> ' . date('F j, Y') . '</p>
                    <p style="margin: 5px 0; font-size: 12px;"><strong>Client:</strong> ' . htmlspecialchars($this->getFullName($user_info)) . '</p>
                </td>
            </tr>
        </table>
        ';
    }
    
    /**
     * Generate PDF footer
     */
    private function generateFooter() {
        return '
        <table width="100%" style="border-top: 1px solid #ccc; padding-top: 10px; margin-top: 20px; font-size: 10px; color: #666;">
            <tr>
                <td width="50%">
                    <p style="margin: 0;">' . $this->default_config['company_name'] . '</p>
                    <p style="margin: 0;">' . $this->default_config['company_phone'] . ' | ' . $this->default_config['company_email'] . '</p>
                </td>
                <td width="50%" style="text-align: right;">
                    <p style="margin: 0;">Page {PAGENO} of {nbpg}</p>
                    <p style="margin: 0;">Generated on ' . date('F j, Y \\a\\t g:i A') . '</p>
                </td>
            </tr>
        </table>
        ';
    }
    
    /**
     * Get document CSS styling
     */
    private function getDocumentCSS() {
        return '
        <style>
            body {
                font-family: "Times New Roman", serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
            }
            
            h1 {
                font-size: 18pt;
                font-weight: bold;
                color: #2c3e50;
                margin: 20px 0 15px 0;
                border-bottom: 2px solid #3498db;
                padding-bottom: 5px;
            }
            
            h2 {
                font-size: 16pt;
                font-weight: bold;
                color: #2c3e50;
                margin: 18px 0 12px 0;
            }
            
            h3 {
                font-size: 14pt;
                font-weight: bold;
                color: #34495e;
                margin: 15px 0 10px 0;
            }
            
            p {
                margin: 0 0 12px 0;
                text-align: justify;
            }
            
            ul, ol {
                margin: 12px 0;
                padding-left: 25px;
            }
            
            li {
                margin: 6px 0;
            }
            
            strong {
                font-weight: bold;
            }
            
            em {
                font-style: italic;
            }
            
            .signature-section {
                margin-top: 40px;
                border-top: 1px solid #ccc;
                padding-top: 20px;
            }
            
            .signature-line {
                border-bottom: 1px solid #000;
                width: 300px;
                height: 40px;
                display: inline-block;
                margin: 10px 20px 5px 0;
            }
            
            .date-line {
                border-bottom: 1px solid #000;
                width: 150px;
                height: 40px;
                display: inline-block;
                margin: 10px 0 5px 0;
            }
            
            .important-notice {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
                margin: 20px 0;
            }
            
            .terms-section {
                font-size: 11pt;
                margin-top: 30px;
            }
            
            @page {
                margin: 20mm;
            }
        </style>
        ';
    }
    
    /**
     * Generate full HTML document (fallback)
     */
    private function generateFullHTML($document, $user_info, $content) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($document['title']) . '</title>
            ' . $this->getDocumentCSS() . '
        </head>
        <body>
            ' . $this->generateHeader($document, $user_info) . '
            <div class="document-content">
                ' . $content . '
            </div>
            ' . $this->generateFooter() . '
        </body>
        </html>
        ';
    }
    
    /**
     * Convert HTML to PDF (basic implementation)
     */
    private function convertHTMLToPDF($html) {
        // This is a placeholder - in production, you'd use wkhtmltopdf or similar
        // For now, we'll return the HTML content as a "PDF"
        return $html;
    }
    
    /**
     * Helper functions
     */
    private function getLoanApplicationData($user_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT la.*, 
                       CONCAT('LF-', YEAR(la.created_at), '-', LPAD(la.id, 4, '0')) as reference_number,
                       CONCAT('APP-', LPAD(la.id, 6, '0')) as application_id
                FROM loan_applications la
                WHERE la.user_id = ?
                ORDER BY la.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error fetching loan application data: " . $e->getMessage());
            return [];
        }
    }
    
    private function getFullName($user_info) {
        if (!empty($user_info['full_name'])) {
            return $user_info['full_name'];
        }
        
        $name_parts = [];
        if (!empty($user_info['first_name'])) {
            $name_parts[] = $user_info['first_name'];
        }
        if (!empty($user_info['last_name'])) {
            $name_parts[] = $user_info['last_name'];
        }
        
        return implode(' ', $name_parts) ?: 'N/A';
    }
    
    private function formatAddress($user_info) {
        $address_parts = [];
        
        if (!empty($user_info['address'])) {
            $address_parts[] = $user_info['address'];
        }
        if (!empty($user_info['city'])) {
            $address_parts[] = $user_info['city'];
        }
        if (!empty($user_info['state'])) {
            $address_parts[] = $user_info['state'];
        }
        if (!empty($user_info['zip_code'])) {
            $address_parts[] = $user_info['zip_code'];
        }
        
        return implode(', ', $address_parts) ?: 'N/A';
    }
    
    private function formatCurrency($amount) {
        return '$' . number_format((float)$amount, 2);
    }
    
    private function formatDate($date) {
        if (empty($date)) {
            return 'N/A';
        }
        
        try {
            return date('F j, Y', strtotime($date));
        } catch (Exception $e) {
            return 'N/A';
        }
    }
    
    private function formatSSN($ssn) {
        if (empty($ssn)) {
            return 'N/A';
        }
        
        // Mask SSN for privacy (show only last 4 digits)
        $ssn = preg_replace('/[^0-9]/', '', $ssn);
        if (strlen($ssn) >= 4) {
            return 'XXX-XX-' . substr($ssn, -4);
        }
        
        return 'XXX-XX-XXXX';
    }
    
    private function generateReferenceNumber($user_id) {
        return 'LF-' . date('Y') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate bulk PDFs for multiple documents
     */
    public function generateBulkPDFs($documents, $user_info) {
        $pdfs = [];
        
        foreach ($documents as $document) {
            try {
                $pdf_content = $this->generateDocumentPDF($document, $user_info);
                $pdfs[] = [
                    'document_id' => $document['id'],
                    'title' => $document['title'],
                    'content' => $pdf_content,
                    'filename' => $this->sanitizeFilename($document['title']) . '.pdf'
                ];
            } catch (Exception $e) {
                error_log("Error generating PDF for document {$document['id']}: " . $e->getMessage());
                $pdfs[] = [
                    'document_id' => $document['id'],
                    'title' => $document['title'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $pdfs;
    }
    
    private function sanitizeFilename($filename) {
        // Remove or replace invalid filename characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $filename);
        $filename = preg_replace('/\s+/', '_', $filename);
        $filename = trim($filename, '_');
        
        return $filename ?: 'document';
    }
    
    /**
     * Add watermark to PDF
     */
    public function addWatermark($pdf_content, $watermark_text = 'CONFIDENTIAL') {
        if ($this->mpdf) {
            $this->mpdf->SetWatermarkText($watermark_text);
            $this->mpdf->showWatermarkText = true;
            $this->mpdf->watermarkTextAlpha = 0.1;
        }
        
        return $pdf_content;
    }
    
    /**
     * Set PDF password protection
     */
    public function setPasswordProtection($password) {
        if ($this->mpdf) {
            $this->mpdf->SetProtection(['print', 'copy'], $password, $password);
        }
    }
}
?>