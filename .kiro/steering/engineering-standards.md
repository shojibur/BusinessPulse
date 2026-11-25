---
inclusion: always
---
# Engineering Standards

- Laravel and Vue conventions first. Use framework features instead of custom code.
- **Prohibited:** Docker/Compose, Redis (Stage 1), microservices, Kubernetes, GraphQL, Kafka/RabbitMQ, event sourcing, CQRS, DDD frameworks, generic workflow engines, rule expression languages, vector DBs, multi-agent systems, LangChain-style frameworks, billing frameworks, multi-tenancy, repository wrappers over Eloquent, speculative abstractions.
- Strict TypeScript. Typed Inertia props in `resources/js/types`.
- Validate all input (Form Requests) and all external provider output (schema validation) before persisting.
- Use DB transactions for multi-record writes; constraints and unique indexes for invariants.
- Long operations run in queued jobs: idempotent, unique per record, bounded retries, `failed()` handler.
- Business logic lives in Services/Actions, not controllers or Vue components. No duplicated calculations.
- Add dependencies only with a stated need. Document env vars in `.env.example`.
- Write focused Laravel feature tests for business-critical behavior (see `verification.md`). No browser automation, property-based frameworks or heavy mocking. Tests do not prove production readiness.
- No interface without a real dependency-direction need or two implementations. No registries, null-object placeholders, or empty directories. Build shared components when the first consumer needs them.
- One retry layer for provider calls: the queue job. `retry_after` must exceed the longest job timeout.
