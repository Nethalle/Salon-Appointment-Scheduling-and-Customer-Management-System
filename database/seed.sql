-- Sample data for local testing only (fake data, demo passwords)
USE salon_db;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('daily_appointment_limit', '20');

-- Demo accounts: admin@salon.test / admin123, staff@salon.test / staff123
INSERT INTO users (name, email, password_hash, role) VALUES
  ('Salon Admin', 'admin@salon.test', '$2y$12$PJs9W.UwPdTt46xetDxPA.8aEQAsyh6PjwbYSp1/3ERRT1QR5P8NC', 'admin'),
  ('Front Desk',  'staff@salon.test', '$2y$12$yjchH/Zn/rdZhFrE0eR4x.c24lI0zCH494lDGG176ONda7p11iQFS', 'staff');

INSERT INTO services (name, price, duration_minutes) VALUES
  ('Haircut',            250.00,  30),
  ('Hair Color',        1200.00, 120),
  ('Rebond',            2500.00, 180),
  ('Manicure',           200.00,  45),
  ('Pedicure',           250.00,  60),
  ('Hair Spa',           600.00,  60);

INSERT INTO stylists (full_name, phone) VALUES
  ('Ana Reyes',   '09170000001'),
  ('Mika Santos', '09170000002'),
  ('Joy Cruz',    '09170000003');

-- Ana: haircut, color, rebond, spa | Mika: haircut, manicure, pedicure | Joy: color, spa, manicure
INSERT INTO stylist_services (stylist_id, service_id) VALUES
  (1,1),(1,2),(1,3),(1,6),
  (2,1),(2,4),(2,5),
  (3,2),(3,6),(3,4);

-- Sample walk-in customers (no account)
INSERT INTO customers (full_name, phone) VALUES
  ('Sample Customer One', '09180000001'),
  ('Sample Customer Two', '09180000002');
