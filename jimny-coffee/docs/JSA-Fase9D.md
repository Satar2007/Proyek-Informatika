# Fase 9D: read-only 120-case benchmark (not independent)

This package adds a **synthetic development benchmark**, not an independent holdout. Cases are deterministically generated, so leakage from training data is possible. No source attestation is provided. A pass **never authorizes promotion**.

## Safety
- Does not write POS records, Laravel controllers, Ollama aliases, scheduler, or model weights.
- Requires existing Fase 9A/9B scripts `jsa_quality_gate_v2.py` and `jsa_semantic_gate.py`.
- Runs 120 prompts per model, potentially several hours on laptop hardware. Run manually when convenient.
- Report includes model answers; review before sharing. It contains only synthetic prompts.
- `--report` refuses overwrite.
- Return code 2 means not improved or blocked; report still saved.

## Next work required before production auto-promotion
1. Independently sourced and access-controlled holdout benchmark, proven disjoint from training and development data.
2. Authentic dataset and adapter provenance, plus measured business quality and privacy/access-control tests.
3. Separate Ollama candidate import and model alias registry; no direct overwrite of `jimny-qwen3:adaptive`.
4. Tested canary, atomic routing change, health checks, and rollback on failure.
5. End-to-end scheduled execution tests with Windows login/power conditions and audit trail.

Training scheduler remains `-Sandbox -SkipDeploy` and does **not** import a candidate. Do not enable unattended promotion before all controls exist.
