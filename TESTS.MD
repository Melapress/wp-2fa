# Automated tests for WP 2FA plugin

This document describes automated tests implemented in WP 2FA plugin.

## Limitations
- the tests do not cover multisite behaviour

## Implemented tests

### Default user meta
Very simple test that checks default user meta and also that correct user meta is set when the settings change to enforce 2FA on all users with no grace period.

### User enforcement state
Set of test that check the user enforcement state change logic in different scenarios. These tests cover the following functions:
- `User::is_enforced`
- `User::run_user_enforcement_check`
- `User::is_excluded`
- `User::run_user_exclusion_check`

### Licensed user count
Set of tests that checks the result of licensed user calculations in `UserLicensing::get_2fa_eligible_user_count`.
