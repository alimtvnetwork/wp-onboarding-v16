# Enum Consumer Checklist

> **Purpose:** Mandatory checklist when adding or modifying any enum case.
> **Updated:** 2026-02-25

## Rule

When a new case is added to any backed enum, **every consumer** of that enum must be updated in the same changeset. A consumer is any file that references the enum by name or iterates over its cases.

## Checklist (apply for every new enum case)

1. [ ] **Enum file** — New case added with correct PascalCase backed value and convenience method (`is{Case}()`).
2. [ ] **Validation/allow-lists** — Any array of allowed values for this enum includes the new case (e.g., `DetectorValidationTrait`).
3. [ ] **UI dropdowns/selects** — All `<select>` or `<option>` lists that render this enum include the new case.
4. [ ] **JS/PHP constant maps** — Any JS or PHP constant object that mirrors enum values includes the new key (e.g., `SNAP_FREQ`).
5. [ ] **Switch/match statements** — Any `switch` or `match` on this enum's value handles the new case explicitly (no silent `default` fallthrough).
6. [ ] **Cron/scheduler registration** — If the enum drives scheduling, the new case is registered as a custom cron interval if WordPress doesn't provide it natively.
7. [ ] **Timing/calculation helpers** — If the enum drives time calculations, the new case has a corresponding calculation method.
8. [ ] **Migration helper** — The `SettingsMigrationHelper::VALUE_MAP` includes a legacy lowercase mapping for the new case.
9. [ ] **UI visibility logic** — Any show/hide logic driven by enum values accounts for the new case (e.g., hiding day/time rows for `Hourly`).
10. [ ] **Memory/inventory docs** — The enum inventory in `.lovable/memory/architecture/php/core-enum-inventory.md` lists the new case.

## Failure Mode

If any consumer is missed, the new case will either:
- Be silently rejected by validation and replaced with a default.
- Be invisible in the UI despite being a valid backend value.
- Cause a `default` branch to fire in a switch, producing incorrect behavior.

## WordPress i18n Text Domain Constraint

> **RULE-I18N-LITERAL** — WordPress i18n function calls (`__()`, `_e()`, `esc_html__()`, `esc_html_e()`, `esc_attr__()`, `esc_attr_e()`, `_n()`, `_x()`) **must** use a **literal string** for the text domain parameter. Constants, variables, or enum values are **prohibited**.

WordPress's `make-pot` CLI performs static analysis and cannot resolve constants or enum expressions. Using anything other than a bare string literal causes silent extraction failure — the `.pot` file will be empty with no runtime error.

**This exemption is permanent and unconditional.** The text domain `'riseup-asia-uploader'` will appear hundreds of times across template and PHP files. It is **not** a magic string violation.

## Known Pitfalls and Prevention

| Issue | Reference |
|-------|-----------|
| i18n text domain must remain a literal string — never replace with enum/constant | [`../02-app-issues/08-i18n-text-domain-literal-requirement.md`](../02-app-issues/08-i18n-text-domain-literal-requirement.md) |
