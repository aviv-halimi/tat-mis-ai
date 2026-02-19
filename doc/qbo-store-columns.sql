-- Store QBO OAuth tokens in dedicated columns (used by qbo_save_tokens / qbo_get_store_params).
-- Run once on the store table. If columns already exist, skip or use your DB’s “add if not exists” pattern.
ALTER TABLE store
  ADD COLUMN qbo_realm_id VARCHAR(64) DEFAULT NULL,
  ADD COLUMN qbo_refresh_token TEXT DEFAULT NULL;
