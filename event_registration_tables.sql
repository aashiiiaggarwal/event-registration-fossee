CREATE TABLE event_registration_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_name VARCHAR(255) NOT NULL,
  event_category VARCHAR(100) NOT NULL,
  event_date INT NOT NULL,
  registration_start INT NOT NULL,
  registration_end INT NOT NULL
);

CREATE TABLE event_registration_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  college VARCHAR(255) NOT NULL,
  department VARCHAR(255) NOT NULL,
  created INT NOT NULL
);
