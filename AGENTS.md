## Database Safety

### Never Run Destructive Database Commands

**Do not run commands that drop, wipe, reset, or recreate a database or its tables** — regardless of flags or environment arguments. Destructive operations include, whatever the stack:

- Framework commands that drop and rebuild the schema (a "fresh", "reset", "refresh", or "wipe" migration command).
- Raw SQL `DROP` or `TRUNCATE` against any database.
- Restoring or re-importing a database over an existing one.

These destroy data. An environment flag (`--env=...`, an alternate connection name) is **not** a safety net — it only helps if a separate, correctly configured environment actually exists. If you are unsure which database a destructive command targets, do not run it.

### Test Database

- The test database is owned by the project's test runner. Let the test suite create, migrate, and tear it down — never migrate or refresh it by hand.
- If the test database gets into a broken state, ask the user to fix it rather than running destructive commands.

### Safe Operations

Safe — these advance or add to the schema without destroying data:

- Running pending migrations **forward** on a non-test database — *after* checking that the pending files only add or alter columns. A forward migration is not automatically safe: it can still drop a column or table, or delete data in a backfill. Read it first.
- Running the test suite (it manages its own database lifecycle).
- Seeding additional data without truncating existing tables.

### When a Destructive Operation Is Genuinely Needed

Stop and ask the user to run it themselves, or to confirm it explicitly. Never decide on your own that data loss is acceptable.

---

## Migrations

Conventions for schema migration files, whatever the migration tool. Examples use a schema-builder DSL for illustration; the principles apply to raw-SQL migrations too.

### Self-Contained Migrations

- Migrations must be fully self-contained. Never reference application code — model constants, enums, config values, or helper functions.
- Use plain string and scalar literals for column names, table names, and other identifiers directly in the migration file.
- This keeps migrations stable and runnable regardless of future application code changes — a migration written today must still run years later, even if the code it once referenced has been renamed or deleted.
- Legacy migrations may still reference application code; only update them to follow this guideline when you are otherwise modifying those migrations.

```php
// ❌ WRONG — references an application constant
$table->boolean(Feature::FLAG_ENABLED)->nullable();

// ✅ CORRECT — plain string literal
$table->boolean('flag_enabled')->nullable();
```

### Column Ordering

- Add new columns at the **end** of the table — do not insert one into the middle of an existing table.
- On MySQL/MariaDB, positioning a column mid-table (an `AFTER` clause) can disable instant/online DDL and force a full table copy — a significant hit on large tables. Other engines such as PostgreSQL have no column-position concept at all, so a position clause is meaningless there. Appending is safe and portable everywhere.

```php
// ❌ WRONG — mid-table positioning can force a full table rebuild on MySQL/MariaDB
$table->string('description')->after('name');

// ✅ CORRECT — just append the column
$table->string('description');
```

---

## Verification Before Completion

Before claiming any work is complete or successful, run the verification command fresh and confirm the output. Evidence before claims, always.

### Required Before Any Completion Claim

1. **Run** the relevant command (in the current message, not from memory)
2. **Read** the full output
3. **Confirm** it supports the claim
4. **Then** state the result with evidence

| Claim            | Required verification                                            |
|------------------|------------------------------------------------------------------|
| Tests pass       | The project's test command, output showing 0 failures            |
| Code style clean | The project's formatter/style checker, output showing no changes |
| Linting clean    | The project's linter, output showing 0 errors                    |
| Types check      | The project's type checker, output showing 0 errors              |
| Bug fixed        | The previously failing test now passes                           |
| Feature complete | All related tests pass                                           |

Use the project's own commands — check its `composer.json` / `package.json` scripts, CI config, or sibling docs to find them. Do not assume a specific tool.

### Delegating the checks

Where the project has dedicated quality-check skills synced, delegate to them — `backend-quality` for backend files, `frontend-quality` for frontend files, both when a change spans both. Otherwise, run the project's own equivalent commands directly.

### Never Use Without Evidence

- "should work now"
- "that should fix it"
- "looks correct"
- "I'm confident this works"

These phrases indicate missing verification. Run the command first, then report what actually happened.
