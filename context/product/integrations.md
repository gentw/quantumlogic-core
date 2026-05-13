# SentriGate — Integrations

## SentriGate + Cloudflare

SentriGate does not replace Cloudflare. It runs on top of it — users keep all CDN, caching, SSL management, and network-level benefits while gaining an additional intelligent security layer.

### The Combined Stack

```
Internet
    |
Cloudflare (DDoS absorption, basic bot filtering, CDN, SSL)
    |
SentriGate Edge (AI threat detection, behavioral analysis)
    |
SentriGate Agent (origin-level intelligence)
    |
Application Backend
```

### Why Use Both?

| Feature | Cloudflare Only | Cloudflare + SentriGate |
|---|---|---|
| DDoS Protection | Yes | Yes + AI monitoring |
| Bot Detection | Basic (rule-based) | Yes + behavioral AI |
| Threat Alerts | None | Real-time + AI recommendations |
| Reporting | Basic | Advanced logs, analytics, attack history |
| Custom AI Rules | None | Predictive AI and traffic patterns |
| API Integration | Partial | Full hosting and cloud integration |
| Behavioral Analysis | None | Full user behavior and anomaly detection |

### Specific Benefits

**1. Double-Layer Protection**
Attacks that Cloudflare's rule-based system does not catch — sophisticated bots, low-and-slow attacks, behavioral anomalies — are detected and stopped by SentriGate's AI layer.

**2. AI Monitoring on Cloudflare-Filtered Traffic**
SentriGate analyzes the traffic that Cloudflare already filtered and looks for:
- Abnormal patterns that don't resemble classic attacks
- Intelligent bots that bypass Cloudflare's bot management
- Suspicious user behavior and unusual request patterns
- Real-time alerts and actionable recommendations

**3. Advanced Reporting**
Cloudflare does not provide AI behavioral analytics. SentriGate adds:
- Reports for every attack that bypassed Cloudflare
- Risk scoring on individual user traffic
- Full attack history and blocked attempt logs

**4. API Integration**
When connected via API, SentriGate can:
- Enable or disable protection rules per domain
- Sync IP whitelists and blacklists between Cloudflare and SentriGate
- Display real-time protection status across both layers in one dashboard

**5. Custom AI Protection Rules**
Beyond Cloudflare's WAF and rate limits, SentriGate provides:
- Blocking based on detected suspicious traffic patterns
- AI predictions for attacks that have not yet happened
- Behavioral blocking for sophisticated, human-like bots

### How to Set Up

No Cloudflare configuration needs to be changed. Connect SentriGate by:
- Adjusting DNS or reverse proxy settings to route traffic through SentriGate
- Or connecting via the Cloudflare API for deeper integration

---

## Shared Hosting Integration

See [`./components.md`](./components.md) — Shared Hosting Mode section for full details.

Summary: Shared hosting provides form protection, bot detection, honeypots, and browser validation. Full reverse proxy and network-layer protection requires VPS or dedicated infrastructure.
