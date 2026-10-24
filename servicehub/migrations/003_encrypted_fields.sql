-- Personal data (name, phone, booking notes) is now stored encrypted, which needs wider columns.
-- Existing rows stay readable. Encrypt them with Admin > Security > "Encrypt existing data".
ALTER TABLE users MODIFY name VARCHAR(600) NULL;
ALTER TABLE users MODIFY phone VARCHAR(255) NULL;
