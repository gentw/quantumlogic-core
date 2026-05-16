# Subscription System Hardening (Laravel + Vue)

## Problem

The current subscription system supports:
- Trial system with abuse protection
- Paid subscriptions
- Auto-renewals via scheduler
- Middleware-based access control
- Invoice generation

However, the system still has risks around:
- trial abuse edge cases
- race conditions in renewals/subscription upgrades
- inconsistent subscription states
- payment method handling during trial
- duplicate invoices or subscriptions
- scheduler overlap issues
- unclear state transitions

---

## Root Cause

The system mixes multiple responsibilities without a strict lifecycle contract:

- Trial creation logic is partially independent from billing logic
- Subscription activation and renewal logic is scattered (API + scheduler)
- Middleware validates access but is not the single source of truth
- Payment method handling is not enforced at trial start
- No strict state machine guarantees valid transitions

---

## Solution

Introduce a **strict subscription lifecycle system** with:
- hardened trial abuse prevention
- mandatory payment method collection during trial (without charging)
- idempotent subscription creation and renewal
- strict state machine rules
- transactional safety for all subscription operations
- single source of truth (`ends_at` + state)
- hardened scheduler execution
- strict invoice integrity rules

---

## Changes Required

---

### 1. Trial Abuse Protection (Critical)

Ensure trial system cannot be reused or bypassed.

#### Required restrictions:
- One trial per `user_id`
- One trial per email (if applicable)
- One trial per IP address (optional but recommended)
- One trial per device fingerprint (if implemented)
- No re-trial after cancellation, expiry, or upgrade

#### Required stored fields:
- `trial_used_at`
- `trial_ip`
- `trial_device_hash`

#### Rules:
- Trial can only be granted once per user lifetime
- Trial upgrade must NOT create a new subscription row if one exists
- Trial end must permanently block re-entry

---

### 2. Subscription State Machine (Mandatory)

Define strict states:

- `trial_active`
- `active`
- `past_due`
- `expired`
- `cancelled`

#### Access rules:
- Allowed:
  - `trial_active` (if not expired)
  - `active` (if `ends_at > now()`)
- Block everything else

---

### 3. Trial Pricing + Payment Method Requirement (Critical Billing Rule)

When a user starts a trial:

#### Pricing rule:
- Subscription price MUST be set to `0.0`
- NO charge is ever created during trial start

#### Payment method rule:
- User MUST provide a valid payment method before trial activation completes
- Payment method is stored as a token (not charged)

#### Required flow:

1. User requests trial
2. System validates eligibility (abuse checks)
3. User provides payment method
4. Subscription is created:
   - `price = 0.0`
   - `state = trial_active`
   - `payment_method_token = stored`
5. NO authorization charge unless explicitly required by provider

#### Important constraint:
- No hidden pre-authorizations unless explicitly defined by payment provider rules

#### Edge cases:
- If user refuses payment method:
  - trial is denied OR limited (business decision)
- If payment method becomes invalid:
  - mark as `trial_active_but_payment_invalid` OR restrict future renewal

---

### 4. Subscription Creation (Idempotent Logic)

Subscribe endpoint must:

- NEVER create duplicate subscriptions
- ALWAYS check existing subscription first

#### Behavior:
- active subscription → extend `ends_at`
- expired subscription → reactivate existing record
- trial → convert into active without new row (preferred)

---

### 5. Race Condition Prevention (Critical)

Wrap all subscription operations in:

- `DB::transaction()`
- `lockForUpdate()` on subscription row

Prevents:
- double renewal
- concurrent trial upgrades
- scheduler + API conflicts

---

### 6. Scheduler Hardening (Auto-Renewals)

Ensure safe execution:

```bash
* * * * * cd /var/www/sentrigate/api && php artisan schedule:run >> /dev/null 2>&1