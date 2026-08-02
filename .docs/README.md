# Laravel Inventory API — developer documentation

Numbered, self-contained docs for the `laravel-inventory-api` repo.

> **New here? Start with [`tldr.md`](tldr.md)** — every document summarised in 30 seconds
> each. Read the full doc only when its summary says it answers your question.

## Who is this for?

| Reader | Start here |
| --- | --- |
| Brand-new developer, fresh PC | [02-setup/getting-started.md](02-setup/getting-started.md) |
| "What even is this repo?" | [01-overview/project-overview.md](01-overview/project-overview.md) |
| Day-2 contributor (branch, code, PR) | [03-development/workflow.md](03-development/workflow.md) |
| Something is broken | [06-troubleshooting/common-issues.md](06-troubleshooting/common-issues.md) |
| "Which command does X?" | [05-reference/commands.md](05-reference/commands.md) |
| AI assistant / Claude session | [`../CLAUDE.md`](../CLAUDE.md) then [tldr.md](tldr.md) |

## Recommended reading order

1. [tldr.md](tldr.md) — the whole set at a glance
2. [01-overview/project-overview.md](01-overview/project-overview.md) — what and why
3. [02-setup/getting-started.md](02-setup/getting-started.md) — get it running
4. [01-overview/architecture.md](01-overview/architecture.md) — how it hangs together
5. [03-development/workflow.md](03-development/workflow.md) — how to contribute
6. Everything else on demand (reference, troubleshooting, FAQ)

## 01-overview

| Document | What it covers |
| --- | --- |
| [project-overview.md](01-overview/project-overview.md) | Features, key design points, what the app is and is not |
| [architecture.md](01-overview/architecture.md) | REST/GraphQL/import request flows, data model, environments, trust boundaries |

## 02-setup

| Document | What it covers |
| --- | --- |
| [getting-started.md](02-setup/getting-started.md) | setup.ps1 → bootstrap → start → import walkthrough, plus the optional MySQL profile |

## 03-development

| Document | What it covers |
| --- | --- |
| [workflow.md](03-development/workflow.md) | Daily loop, where changes go, testing patterns, git/PR conventions, data hygiene |

## 04-deployment

| Document | What it covers |
| --- | --- |
| [deployment.md](04-deployment/deployment.md) | Honest current state (none) + the real checklist if it ever ships |

## 05-reference

| Document | What it covers |
| --- | --- |
| [commands.md](05-reference/commands.md) | Every just recipe, PORT override, useful raw artisan calls |
| [project-layout.md](05-reference/project-layout.md) | Annotated file tree and naming conventions |

## 06-troubleshooting

| Document | What it covers |
| --- | --- |
| [common-issues.md](06-troubleshooting/common-issues.md) | Real verified symptoms: GraphQL path, queue, testing.sqlite, lingering uploads, Vite 500, ports |

## 07-faq

| Document | What it covers |
| --- | --- |
| [faq.md](07-faq/faq.md) | Queue rationale, import math, response envelope, sqlite vs MySQL, Vue-frontend pairing, auth status |
