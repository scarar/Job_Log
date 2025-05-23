CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_applied DATE NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    position_title VARCHAR(100) NOT NULL,
    job_description TEXT,
    application_method VARCHAR(50),
    job_posting_url VARCHAR(255),
    follow_up_date DATE,
    status ENUM('Applied', 'Interviewing', 'Offer', 'Rejected', 'Accepted') DEFAULT 'Applied',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
); 