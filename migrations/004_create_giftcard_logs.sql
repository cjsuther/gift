CREATE TABLE IF NOT EXISTS giftcard_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    giftcard_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    action ENUM('created','viewed','redeemed','cancelled','edited') NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (giftcard_id) REFERENCES giftcards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_giftcard (giftcard_id),
    INDEX idx_action_date (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
