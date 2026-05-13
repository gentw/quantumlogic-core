# SentriGate — Components

## 1. SentriGate Edge

The Edge is the outermost layer of protection. It operates before requests ever reach your infrastructure.

### Responsibilities

| Function | Detail |
|---|---|
| DDoS Mitigation | Absorbs SYN floods, HTTP floods, and volumetric attacks |
| JS Browser Validation | Verifies that requests come from real browsers |
| Bot Filtering | Blocks scrapers, credential stuffers, and automated abusers |
| Request Reputation Scoring | Every incoming request receives a risk score |
| Rate Limiting | Enforces per-IP and per-route request limits |
| ASN & Geo Filtering | Block or challenge traffic by network or country |
| Challenge Orchestration | Issues browser challenges to suspicious traffic |
| Distributed IP Reputation | Leverages global threat intelligence across all protected sites |
| CDN-style Traffic Filtering | Absorbs and filters traffic before forwarding clean requests |

### How It Works

The Edge sits in front of your entire infrastructure. Traffic enters the Edge first, where it is evaluated against:
- Known bad IPs and ASNs from the global reputation database
- Behavioral signatures for bots and attack tools
- Rate and flood thresholds
- Active challenge requirements

Only traffic that passes the Edge proceeds to your origin infrastructure.

---

## 2. SentriGate Agent

The Agent is a lightweight, universal binary that runs directly on your VPS or dedicated server. It is the intelligence layer at your origin.

### Key Principle

The Agent does NOT handle or proxy traffic by itself. It acts as a decision helper that communicates intelligence between your origin and the SentriGate Core, then pushes resulting rules to the Edge layer.

Think of the Agent as intelligence, not a traffic handler.

### Security Functions

- Receives dynamic rules from SentriGate Core (IP blocks, geo blocks, bot signatures)
- Validates headers, cookies, and JWTs at the application level
- Detects anomalies in request patterns
- Optionally scans uploaded content hashes for malware
- Provides an application-level firewall on top of the WAF

### Optimization Functions

- Reports which endpoints are cacheable and their performance metrics
- Identifies heavy or slow endpoints for pre-caching
- Collects analytics on traffic patterns to help the Core optimize routing
- Generates dynamic PHP/backend bypass rules — routes that don't need backend execution are served from cache

### Universal Compatibility

The Agent is framework-agnostic. It works at the HTTP layer and does not depend on:
- Laravel internals or service providers
- WordPress hooks or plugins
- Spring Boot internals
- Django middleware
- ORM or database logic
- Any application business logic

It works identically across all supported platforms.

### Supported Platforms

PHP · WordPress · Laravel · Symfony · Node.js · Python · Java · Ruby · .NET · Custom HTTP services

---

## 3. SentriGate Core

The Core is the central brain of the SentriGate platform. It does not sit in the traffic path — it operates in the background, continuously learning and issuing decisions.

### What Core Aggregates

- Traffic patterns from all protected sites
- Bot behavior and fingerprint data
- Risk events and attack signatures
- Agent metadata and anomaly reports
- WAF events
- Global IP reputation data
- Behavioral signatures

### What Core Produces

- **Bypass rules** — which URIs can be served without backend execution
- **Cache rules** — what content to serve from the Edge cache
- **Block rules** — IPs, ASNs, user agents, and geos to block or challenge
- **Emergency rules** — temporary aggressive defense during active attacks (can fully disable backend forwarding during large-scale attacks)
- **AI predictions** — proactive blocking based on learned attack patterns

### Shared Intelligence

Threat intelligence is shared across all sites protected by SentriGate. An attack pattern detected on one customer's site is used to protect all other sites immediately — without any manual configuration.

---

## 4. Shared Hosting Mode

On shared hosting environments, SentriGate operates with a reduced but still valuable feature set.

### Available on Shared Hosting

- Form protection and honeypots
- Bot detection and browser validation
- Request analysis and anomaly detection
- Challenge signaling

### Not Available on Shared Hosting

Shared hosting environments do not permit:
- Reverse proxy installation
- Kernel-level firewall access
- Low-level network filtering
- Full backend bypass

### Recommendation

For full protection, optimization, and all SentriGate capabilities, a VPS or dedicated server is required. Shared hosting mode provides meaningful protection but is not equivalent to the full deployment.
