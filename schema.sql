CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100), -- nullable, only for client
    last_name VARCHAR(100), -- nullable, only for client
    mobile_number VARCHAR(20) UNIQUE, -- nullable, only for client
    password_hash VARCHAR(255) NOT NULL,

    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)

CREATE TABLE courts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)

CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    effective_datetime DATETIME NOT NULL, -- This needs a business rule to finalize
    court_id INT NOT NULL,
    user_id INT NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (court_id) REFERENCES courts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE sms_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message TEXT NOT NULL

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)


-- These tables our probably enough to start with developing the simplest of modules