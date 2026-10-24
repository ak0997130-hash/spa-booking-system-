-- Mobile numbers are no longer verified, so they no longer need to be unique.
-- Only needed if you ran an earlier version of 001 that added a unique phone index; otherwise this step is skipped automatically.
-- (A leftover users.phone_verified_at column is harmless and kept so no data is lost.)
ALTER TABLE users DROP INDEX uq_users_phone;
