<?php
/**
 * Holiday-Aware Email Scheduler
 * Prevents email sending on holidays and Sundays based on country-specific rules
 */

require_once 'database.php';
require_once 'functions.php';
require_once 'audit_logger.php';

class HolidayEmailScheduler {
    private $pdo;
    private $audit_logger;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->audit_logger = new AuditLogger();
    }
    
    /**
     * Check if email sending is allowed on a specific date for a country
     * @param string $date Date in Y-m-d format
     * @param string $country_code Country code (USA, CAN, etc.)
     * @return bool True if email sending is allowed
     */
    public function isEmailSendingAllowed($date, $country_code) {
        // Check if it's Sunday (day 0)
        $dayOfWeek = date('w', strtotime($date));
        if ($dayOfWeek == 0) {
            return false; // No emails on Sundays
        }
        
        // Check if it's a holiday
        if ($this->isHoliday($date, $country_code)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if a specific date is a holiday for a country
     * @param string $date Date in Y-m-d format
     * @param string $country_code Country code
     * @return bool True if it's a holiday
     */
    public function isHoliday($date, $country_code) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM holidays 
            WHERE country_code = ? AND holiday_date = ?
        ");
        
        $stmt->execute([$country_code, $date]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    /**
     * Get the next available email sending date
     * @param string $country_code Country code
     * @param string $start_date Starting date (default: today)
     * @return string Next available date in Y-m-d format
     */
    public function getNextAvailableEmailDate($country_code, $start_date = null) {
        if ($start_date === null) {
            $start_date = date('Y-m-d');
        }
        
        $current_date = $start_date;
        $max_attempts = 30; // Prevent infinite loop
        $attempts = 0;
        
        while ($attempts < $max_attempts) {
            if ($this->isEmailSendingAllowed($current_date, $country_code)) {
                return $current_date;
            }
            
            // Move to next day
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            $attempts++;
        }
        
        // Fallback: return date 30 days from start if no valid date found
        return date('Y-m-d', strtotime($start_date . ' +30 days'));
    }
    
    /**
     * Schedule an email with holiday awareness
     * @param array $email_data Email data including recipient, subject, body, etc.
     * @param string $country_code Country code for holiday checking
     * @param string $preferred_date Preferred sending date (optional)
     * @return array Result with scheduled date and status
     */
    public function scheduleEmail($email_data, $country_code, $preferred_date = null) {
        try {
            $scheduled_date = $preferred_date ?? date('Y-m-d');
            
            // Find next available date if preferred date is not suitable
            if (!$this->isEmailSendingAllowed($scheduled_date, $country_code)) {
                $scheduled_date = $this->getNextAvailableEmailDate($country_code, $scheduled_date);
            }
            
            // Insert into email queue with scheduled date
            $stmt = $this->pdo->prepare("
                INSERT INTO email_queue 
                (recipient_email, recipient_name, subject, body, template_name, 
                 country_code, scheduled_date, status, created_at, priority) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW(), ?)
            ");
            
            $result = $stmt->execute([
                $email_data['recipient_email'],
                $email_data['recipient_name'] ?? '',
                $email_data['subject'],
                $email_data['body'],
                $email_data['template_name'] ?? 'default',
                $country_code,
                $scheduled_date,
                $email_data['priority'] ?? 'normal'
            ]);
            
            if ($result) {
                $email_id = $this->pdo->lastInsertId();
                
                // Log the scheduling
                $this->audit_logger->logActivity([
                    'user_id' => $_SESSION['user_id'] ?? 'system',
                    'action' => 'email_scheduled',
                    'details' => [
                        'email_id' => $email_id,
                        'recipient' => $email_data['recipient_email'],
                        'country_code' => $country_code,
                        'scheduled_date' => $scheduled_date,
                        'original_date' => $preferred_date
                    ]
                ]);
                
                return [
                    'success' => true,
                    'email_id' => $email_id,
                    'scheduled_date' => $scheduled_date,
                    'message' => 'Email scheduled successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to schedule email'
            ];
            
        } catch (Exception $e) {
            error_log("Email scheduling error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error scheduling email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get scheduled emails for a specific date range
     * @param string $start_date Start date
     * @param string $end_date End date
     * @param string $country_code Optional country filter
     * @return array List of scheduled emails
     */
    public function getScheduledEmails($start_date, $end_date, $country_code = null) {
        $sql = "
            SELECT eq.*, h.holiday_name 
            FROM email_queue eq
            LEFT JOIN holidays h ON eq.scheduled_date = h.holiday_date AND eq.country_code = h.country_code
            WHERE eq.scheduled_date BETWEEN ? AND ?
        ";
        
        $params = [$start_date, $end_date];
        
        if ($country_code) {
            $sql .= " AND eq.country_code = ?";
            $params[] = $country_code;
        }
        
        $sql .= " ORDER BY eq.scheduled_date ASC, eq.priority DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Process emails scheduled for today
     * @return array Processing results
     */
    public function processScheduledEmails() {
        $today = date('Y-m-d');
        $processed = 0;
        $errors = 0;
        
        try {
            // Get emails scheduled for today
            $stmt = $this->pdo->prepare("
                SELECT * FROM email_queue 
                WHERE scheduled_date = ? AND status = 'scheduled'
                ORDER BY priority DESC, created_at ASC
            ");
            
            $stmt->execute([$today]);
            $emails = $stmt->fetchAll();
            
            foreach ($emails as $email) {
                // Double-check if sending is still allowed
                if (!$this->isEmailSendingAllowed($today, $email['country_code'])) {
                    // Reschedule for next available date
                    $next_date = $this->getNextAvailableEmailDate($email['country_code'], $today);
                    $this->rescheduleEmail($email['id'], $next_date);
                    continue;
                }
                
                // Send the email
                if ($this->sendScheduledEmail($email)) {
                    $processed++;
                } else {
                    $errors++;
                }
            }
            
            return [
                'success' => true,
                'processed' => $processed,
                'errors' => $errors,
                'total' => count($emails)
            ];
            
        } catch (Exception $e) {
            error_log("Error processing scheduled emails: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reschedule an email to a new date
     * @param int $email_id Email ID
     * @param string $new_date New scheduled date
     * @return bool Success status
     */
    private function rescheduleEmail($email_id, $new_date) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE email_queue 
                SET scheduled_date = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            return $stmt->execute([$new_date, $email_id]);
        } catch (Exception $e) {
            error_log("Error rescheduling email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send a scheduled email
     * @param array $email Email data from queue
     * @return bool Success status
     */
    private function sendScheduledEmail($email) {
        try {
            // Use existing email system to send
            require_once 'email_system.php';
            $email_system = new EmailTemplateSystem();
            
            $result = $email_system->sendEmail(
                $email['recipient_email'],
                $email['subject'],
                $email['body'],
                $email['recipient_name']
            );
            
            if ($result) {
                // Update status to sent
                $stmt = $this->pdo->prepare("
                    UPDATE email_queue 
                    SET status = 'sent', sent_at = NOW(), updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$email['id']]);
                
                // Log the sending
                $this->audit_logger->logActivity([
                    'user_id' => 'system',
                    'action' => 'scheduled_email_sent',
                    'details' => [
                        'email_id' => $email['id'],
                        'recipient' => $email['recipient_email'],
                        'country_code' => $email['country_code']
                    ]
                ]);
                
                return true;
            } else {
                // Update status to failed
                $stmt = $this->pdo->prepare("
                    UPDATE email_queue 
                    SET status = 'failed', updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$email['id']]);
                
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Error sending scheduled email: " . $e->getMessage());
            
            // Update status to failed
            $stmt = $this->pdo->prepare("
                UPDATE email_queue 
                SET status = 'failed', error_message = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $email['id']]);
            
            return false;
        }
    }
    
    /**
     * Get holiday statistics for reporting
     * @param string $country_code Optional country filter
     * @return array Holiday statistics
     */
    public function getHolidayEmailStats($country_code = null) {
        $stats = [];
        
        // Emails rescheduled due to holidays
        $sql = "
            SELECT COUNT(*) as count 
            FROM email_queue eq
            JOIN holidays h ON eq.scheduled_date = h.holiday_date AND eq.country_code = h.country_code
            WHERE eq.status IN ('scheduled', 'sent')
        ";
        
        $params = [];
        if ($country_code) {
            $sql .= " AND eq.country_code = ?";
            $params[] = $country_code;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats['rescheduled_for_holidays'] = $stmt->fetch()['count'];
        
        // Emails scheduled for weekends (Sundays)
        $sql = "
            SELECT COUNT(*) as count 
            FROM email_queue 
            WHERE DAYOFWEEK(scheduled_date) = 1 AND status IN ('scheduled', 'sent')
        ";
        
        if ($country_code) {
            $sql .= " AND country_code = ?";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats['rescheduled_for_weekends'] = $stmt->fetch()['count'];
        
        return $stats;
    }
    
    /**
     * Get upcoming holidays that might affect email scheduling
     * @param string $country_code Country code
     * @param int $days_ahead Number of days to look ahead (default: 30)
     * @return array List of upcoming holidays
     */
    public function getUpcomingHolidays($country_code, $days_ahead = 30) {
        $end_date = date('Y-m-d', strtotime("+{$days_ahead} days"));
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM holidays 
            WHERE country_code = ? 
            AND holiday_date BETWEEN CURDATE() AND ?
            ORDER BY holiday_date ASC
        ");
        
        $stmt->execute([$country_code, $end_date]);
        return $stmt->fetchAll();
    }
}

?>