---
layout: post
title: Confluence agile structure structure blueprint
excerpt_separator: <!--more-->
---

How to structure software project using Confluence for agile/scrum team of 5-7 engineers.

<!--more-->

### Agile Engineering Team Confluence template structure

Example structure limits depth to 4 levels.
 - Root level should only contain folders
 - Document names are prefixed with YYYY-MM-DD date format

```
Project-B/
├─ 💼 Project BRIEF
│  
├─ 🗃 Artifacts/
│  ├─ 💬 Request for Comments (RFC) /
│  │  ├─ Draft/
│  │  ├─ Review/
│  │  ├─ Approved/
│  │  └─ Expired & Rejected/
│  │
│  ├─ Architecture Decision Records (ADR) /
│  │  ├─ Draft/
│  │  ├─ Review/
│  │  ├─ Approved/
│  │  └─ Expired & Rejected/
│  │
│  │─ 📖 Knowledge Silo /
│  │   ├─ Project-A/
│  │   │  ├─ 2025-01-01 Events, Commands, Actors
│  │   │  └─ 2025-01-01 Glossary of Terms
│  │   └─ Project-B/
│  │      └─ 2025-01-01 DB Schema and Entities
│  │
│  ├─ 🚨 Incidents/
│  │  └─ 2025-01-01 SQL Server storage capacity
│  │
│  ├─ 🔍 Hiring /
│  │
│  ├─ 🤝 Meeting Notes/
│  │  ├─ Scrum Events/
│  │  │  ├─ 2025-01-01 Sprint Review
│  │  │  └─ 2025-01-02 Sprint Retro
│  │  ├─ 2025-01-01 Agile LiftOff
│  │  └─ 2025-01-01 Prospective Analysis
│  │
│  ├─ ⚗️ Research & Devepment Lab/
│  │  ├─ Microservices patterns
│  │  └─ Restful API 101
│  │
│  ├─ 🛤 Roadmap, Events, Timeline
│  │
│  └─ 🚢 Releases
│     ├─ 2019/
│     └─ 2020/
│  
├─ Toolchain/
│  ├─ Go Programming Language
│  ├─ How to Kubernetes
│  ├─ Docker
│  ├─ Contributing
│  ├─ Code of Conduct
│  └─ License
├─ Processes and policies/
│  ├─ Organizational
│  │  ├─ Adoption of Scrum
│  │  ├─ Communication channels
│  │  ├─ Meetings culture
│  │  ├─ Working hours
│  │  ├─ Quality Assurance
│  │  ├─ SLO & SLI
│  ├─ Engineering Practices
│  │  ├─ Pair programming
│  │  ├─ DDD
│  │  ├─ Microservices
│  │  ├─ Secure by design
│  │  ├─ Clean code
│  │  ├─ Code quality standards
│  │  ├─ Code reviews
│  │  ├─ Observability
│  │  ├─ Git branching model
│  │  ├─ Trunk based development
│  │  ├─ Code desgn proverbs

```