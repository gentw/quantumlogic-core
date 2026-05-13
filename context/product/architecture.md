# SentriGate — Architecture

## Request Flow Overview

```
Client Browser
      |
      v
SentriGate Edge
(DDoS / JS Challenge / Bot Filtering)
      |
      v
Edge Layer (Reverse Proxy)
      |
      ├─ WAF (known threats)
      ├─ Bot / IP filtering
      ├─ Cache / Static serving
      |
      ├─ SentriGate Decision (FAST path)
      |
      └─ Application Backend (LAST resort)
             |
             └─ SentriGate Agent (metadata + intelligence)
                      |
                      └─ SentriGate Core (brain)
```

## Before and After

### Without SentriGate
```
Internet → Edge Layer → Application Backend
```

### With SentriGate
```
Internet → SentriGate Edge → Edge Layer → SentriGate Agent → Application Backend
```

This architecture enables:
- Request inspection and filtering before the backend ever executes
- Safe caching at the edge layer to reduce backend load
- Backend shielding — your origin server is never directly exposed
- Intelligent request decisions driven by the SentriGate Core

## Design Principles

### The Backend Is the Last Resort

The application backend (PHP, Node.js, Python, etc.) should only execute when absolutely necessary. The Edge layer makes every effort to:
1. Serve cached or static content
2. Challenge or block suspicious requests
3. Absorb attack traffic entirely

Only clean, verified requests reach the backend.

### Intelligence Flows Upward

The SentriGate Agent embedded at the origin does not handle traffic directly. Its role is to:
- Report behavior, metrics, and anomalies to the Core
- Receive updated rules and push them to the Edge layer
- Generate dynamic bypass and caching rules based on real traffic patterns

### The Core Is the Brain

SentriGate Core aggregates:
- Traffic patterns across all protected sites
- Bot behavior and fingerprints
- Agent metadata and anomaly reports
- WAF events and blocked request data
- Global IP reputation

It then issues:
- Bypass rules (which routes can skip the backend entirely)
- Cache rules (what to serve from cache)
- Block rules (IPs, ASNs, user agents, geos)
- Emergency rules (temporary lockdown during active attacks)

### Threat Intelligence Is Shared

Threats detected on one site inform the protection of all sites. Attack patterns, bot fingerprints, and malicious IP ranges are aggregated at the Core and distributed globally.

## Communication Model

The SentriGate Agent communicates bi-directionally with the Core:

**Push (Agent → Core)**
- Traffic metrics and request patterns
- Detected anomalies
- Response latency and endpoint performance data
- Uploaded content hashes (for virus/malware scanning)

**Pull (Agent ← Core)**
- Updated security rules
- Caching instructions
- IP/ASN block lists
- Emergency attack-mode directives

Communication options:
- HTTP REST (default, simple)
- WebSocket or gRPC (for real-time rule delivery)

The Agent caches rules locally to remain functional during any network interruption between Agent and Core.
