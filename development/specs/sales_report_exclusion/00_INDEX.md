# Sales Report Exclusion System - Specification Index

## Overview

This specification documents the implementation of a stealth mode for excluding specific items from sales reporting while maintaining original receipt data for printing and audit trails.

## Document Structure

| File | Description | Component |
|------|-------------|-----------|
| [00_INDEX.md](00_INDEX.md) | This file - Specification index | All |
| [01_OVERVIEW.md](01_OVERVIEW.md) | System overview and requirements | All |
| [02_ARCHITECTURE.md](02_ARCHITECTURE.md) | High-level architecture | All |
| [10_BACKEND_SPEC.md](10_BACKEND_SPEC.md) | Backend implementation (Laravel) | apex_backend |
| [20_POS_SPEC.md](20_POS_SPEC.md) | POS terminal implementation (Flutter) | apex_pos |
| [30_DASHBOARD_SPEC.md](30_DASHBOARD_SPEC.md) | Dashboard implementation (Flutter) | apex_dashboard |
| [40_SECURITY_SPEC.md](40_SECURITY_SPEC.md) | Security and stealth considerations | All |
| [50_TESTING_SPEC.md](50_TESTING_SPEC.md) | Testing strategy and acceptance criteria | All |
| [60_IMPLEMENTATION_ROADMAP.md](60_IMPLEMENTATION_ROADMAP.md) | Implementation phases and timeline | All |

## Quick Links

- **Backend Spec:** [10_BACKEND_SPEC.md](10_BACKEND_SPEC.md)
- **POS Spec:** [20_POS_SPEC.md](20_POS_SPEC.md)
- **Dashboard Spec:** [30_DASHBOARD_SPEC.md](30_DASHBOARD_SPEC.md)
- **Implementation Roadmap:** [60_IMPLEMENTATION_ROADMAP.md](60_IMPLEMENTATION_ROADMAP.md)

## Status

- [ ] Specification Review
- [ ] Approval
- [ ] Implementation Phase 1 (Backend Core)
- [ ] Implementation Phase 2 (Historical Tracking)
- [ ] Implementation Phase 3 (POS Integration)
- [ ] Implementation Phase 4 (Dashboard Integration)
- [ ] Testing
- [ ] Deployment

## Related Documents

- [Existing Priority Column Implementation](../) (referenced in requirements)
- [Hocus Pocus Route](../) (existing route for toggles)
