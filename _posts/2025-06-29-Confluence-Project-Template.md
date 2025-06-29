---
layout: post
title: Confluence project structure blueprint
excerpt_separator: <!--more-->
---

How to structuring example software project with Confluence.

<!--more-->

### Project Confluence structure

Example structure limits depth to 4 levels.
 - Root level should only contain folders
 - Document names are prefixed with YYYY-MM-DD date format when applicable

```
Project-A/
├─ 00 Project BRIEF
├─ 01 Artifacts/
│  ├─ Meetings/
│  │  ├─ Scrum Events/
│  │  │  ├─ 2025-01-01 Sprint Review
│  │  │  ├─ 2025-01-02 Sprint Retro
│  │  ├─ 2025-01-01 Agile LiftOff
│  │  ├─ 2025-01-01 Prospective Analysis
│  ├─ R&D Lab/
│  │  ├─ Microservices patterns
│  │  ├─ Restful API 101
│  ├─ Incidents/
│  │  ├─ 2025-01-01 SEV-2 Orders delivery
│  ├─ Domain Knowledge/
│  │  ├─ Domain events
│  │  ├─ Domain actors
│  │  ├─ Domain boundary
├─ 02 Toolchain/
│  ├─ Go Programming Language
│  ├─ Kubernetes
│  ├─ Docker
│  ├─ Contributing
│  ├─ Code of Conduct
│  ├─ License
├─ 03 Processes and policies/
│  ├─ Project management and SDLC
│  │  ├─ Adoption of Scrum
│  │  ├─ Communication channels
│  │  ├─ Meetings culture
│  │  ├─ Working hours
│  │  ├─ Quality Assurance
│  │  ├─ SLO & SLI
│  ├─ Software Engineering Practices
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

#### Project BRIEF

Project BRIEF is the document which is created in early planning stages of the project.
There are many documents online describing what the contents should look like.

The goal is to be able to communicate to business what is the project about, it scope and budget.
It is also a medium for formal leadership approval for the project.

- [Crafting a Software Project Brief](https://smithingsystems.com/insights/crafting-a-software-project-brief)
- [How to Write a Brief for a Software Project](https://adevait.com/blog/startups/write-brief-for-software-project)

###### BRIEF structure
- Project name
- Logo
- Description
- Sponsor
- Team
- Started/Approved/Estimate dates
- Short Summary
- Goals, primary goals and secondary goals (vision)
- Tasks outlining how the goals are to be reached (missions)
- Requirements: functional, optional, non-functional, technical, business

#### Artifacts

Collection of resources: documents, meeting notes, audio/video resources that are produced "by the team" while working on project.

#### Toolchain

Tools used by the team while working on the project, why they were used and how to use them properly. See [SBOM](https://en.wikipedia.org/wiki/Software_supply_chain).

#### Processes and policies

Team may agree on common standards and best practices.
 - *Best practices in software engineering are a collection of proven methods and techniques that have been shown to improve the quality, reliability, and maintainability of software systems* to follow and share while working together.

Also includes policies to follow department/company wide processes and policies, in which case a simple reference is sufficient.

