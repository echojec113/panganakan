# Maternity CDSS Project Instructions

## Project Overview

This repository contains an existing Laravel 12 maternity Clinical Decision
Support System for prenatal risk assessment in a Philippine lying-in clinic.

Technology stack:

- Laravel 12
- PHP 8.3
- MySQL
- Python
- Random Forest model
- Blade and Tailwind CSS

The system already works. Improve it incrementally. Do not rebuild it from
scratch.

---

## Current Implementation Status

The prenatal risk-assessment logic has been extracted from
`PrenatalVisitController` into:

`app/Services/RiskAssessmentService.php`

`PrenatalVisitController` receives this service through constructor dependency
injection.

The following controllers resolve `PrenatalVisitController` through Laravel's
service container when triggering recalculation:

- `BirthPlanController`
- `MedicalHistoryController`
- `UltrasoundController`

Never restore direct construction such as:

```php
new PrenatalVisitController()


## Development Principles (Always Follow)

This project is developed incrementally like a production Clinical Decision Support System.

Every sprint MUST include:

1. Backend implementation
2. UI/UX improvements (when applicable)
3. Testing (unit/integration/manual)
4. Documentation updates
5. Defense notes (explain design decisions)

Rules:

- Never rewrite working code when refactoring.
- Preserve clinical behavior unless explicitly instructed.
- Read AGENTS.md and IMPLEMENTATION_PROGRESS.md before making changes.
- Update IMPLEMENTATION_PROGRESS.md after every completed sprint.
- Explain architectural decisions before implementation.
- Prefer small services over large controllers.
- Never change clinical thresholds without approval.
- Every change must preserve patient safety and explainability.