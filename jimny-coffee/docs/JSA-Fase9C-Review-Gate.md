# JSA Fase 9C: Offline Promotion Review Gate

This package adds a **review-only** promotion eligibility decision. It does **not** change the Ollama model, Laravel controllers, scheduled tasks, or any POS data.

## Why it is not an automatic promotion system yet

The existing Fase 6 monthly task runs `-Sandbox -SkipDeploy` and produces an adapter, not an evaluated Ollama candidate. The six-case Fase 9B smoke test is not an independent held-out benchmark. Current training source is `csv_unverified_origin`. There is no tested transactional alias switch, health-check, and rollback path. These are blocking requirements.

## Review contract

`jsa_promotion_gate.py` reads a JSON evidence object and writes a fresh report. It requires at least 100 independent held-out cases, no overlap, verified provenance and integrity, zero critical failures, a minimum 5-percentage-point improvement, latency no more than 150% of the incumbent, and a verified rollback. All missing or malformed fields fail closed. These thresholds are provisional engineering policy, not statistically validated.

**Security boundary:** JSON fields such as `source_verified`, `independent_heldout`, `provenance_attested`, and `rollback_verified` are *claims*. This tool does not verify signatures, manifests, or test execution. `eligible_for_review=true` does not authorize production promotion. `promotion_executed` is always `false`.

## Next work before unattended production promotion

1. Create candidate import into a sandbox alias and evaluate both models against a versioned, independently held-out business benchmark (with source provenance and data leakage checks).
2. Add trusted evidence generation from signed/hash-verified artifacts, not manually supplied booleans.
3. Implement atomic or safely reversible model routing with a backup pointer, smoke health checks, a lock to prevent concurrent changes, and automatic rollback on errors.
4. Run integration tests in a staging Laravel/Ollama environment, including interrupted deployment and degraded response behavior.
5. Require human review of the first successful promotion, then consider enabling unattended promotion.

No script in this package promotes or changes any model.
