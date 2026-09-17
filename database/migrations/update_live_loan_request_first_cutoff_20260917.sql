-- Run once on the live database before deploying the PHP changes.
-- Schema only: existing loan requests and payments are not modified.
ALTER TABLE `loan_requests`
  ADD COLUMN `first_payment_cutoff` DATE NULL AFTER `requested_months`;
