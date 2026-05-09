# Documentation

| Document | Description |
|----------|-------------|
| [api-v1-authentication.md](api-v1-authentication.md) | **Integrator / user manual** for API v1 auth — modes (`LOGIN_TYPE`), OAuth2 (Passport), endpoints, example bodies and responses, OTP flows |
| [ride-lifecycle-api-v1.md](ride-lifecycle-api-v1.md) | Ride request lifecycle, role-based endpoints (user/driver/admin), transition rules, scheduler-driven expiry, and timeline logging |

The **implementation roadmap** for OTP hardening lives only in the Cursor plan (`otp_endpoints_and_hardening_80c29137.plan.md`), not in this folder.

After roadmap items (cooldown, optional `/otp/resend`, etc.) are implemented, refresh **api-v1-authentication.md** so it stays accurate for operators.
