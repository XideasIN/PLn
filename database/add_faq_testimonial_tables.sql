-- Add FAQ and Testimonial tables for admin-editable content

-- Create FAQs table
CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Testimonials table
CREATE TABLE IF NOT EXISTS testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    client_title VARCHAR(255),
    testimonial_text TEXT NOT NULL,
    client_image VARCHAR(255),
    rating TINYINT(1) DEFAULT 5 CHECK (rating >= 1 AND rating <= 5),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample FAQs
INSERT INTO faqs (question, answer, display_order, is_active) VALUES
('How quickly can I get approved for a loan?', 'Most loan applications are processed within 24-48 hours. Once approved, funds are typically disbursed within 1-3 business days.', 1, 1),
('What documents do I need to apply?', 'You will need a valid ID, proof of income, bank statements, and employment verification. Additional documents may be required based on loan type.', 2, 1),
('What are your interest rates?', 'Our interest rates are competitive and vary based on loan amount, term, and your credit profile. Rates start from as low as 5.99% APR.', 3, 1),
('Can I pay off my loan early?', 'Yes, you can pay off your loan early without any prepayment penalties. This can help you save on interest charges.', 4, 1),
('What loan amounts do you offer?', 'We offer personal loans ranging from $1,000 to $50,000, depending on your income and creditworthiness.', 5, 1),
('Is my personal information secure?', 'Absolutely. We use bank-level encryption and security measures to protect all your personal and financial information.', 6, 1),
('What if I have bad credit?', 'We work with borrowers of all credit types. While better credit may qualify for lower rates, we have options for those with less-than-perfect credit.', 7, 1);

-- Insert sample testimonials
INSERT INTO testimonials (client_name, client_title, testimonial_text, rating, display_order, is_active) VALUES
('Sarah Johnson', 'Small Business Owner', 'QuickFunds helped me get the capital I needed to expand my business. The process was smooth and the team was incredibly supportive throughout.', 5, 1, 1),
('Michael Chen', 'Marketing Manager', 'I needed funds for home renovations and QuickFunds delivered exactly what they promised. Fast approval and competitive rates!', 5, 2, 1),
('Emily Rodriguez', 'Teacher', 'The customer service was exceptional. They walked me through every step and made sure I understood all the terms before signing.', 5, 3, 1),
('David Thompson', 'Engineer', 'I was skeptical about online lending, but QuickFunds proved to be trustworthy and professional. Highly recommended!', 4, 4, 1),
('Lisa Williams', 'Nurse', 'Quick approval, fair terms, and excellent communication. QuickFunds made getting a personal loan stress-free.', 5, 5, 1);

-- Create indexes for better performance
CREATE INDEX idx_faqs_active_order ON faqs (is_active, display_order);
CREATE INDEX idx_testimonials_active_order ON testimonials (is_active, display_order);