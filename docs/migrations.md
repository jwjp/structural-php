# Migrations

Code: [`system/Database/Migrator.php`](../system/Database/Migrator.php), [`bin/migrate`](../bin/migrate) · Tests: [`tests/mysql.php`](../tests/mysql.php)

## Why

A single `schema.sql` describes the final schema but not how to get there. Once a database exists, adding a column means writing an `ALTER TABLE` by hand on every machine and remembering which ones already ran it. Migrations keep each change as its own file and record which files each database has applied.

## Commands

```bash
php bin/migrate status                      # pending or batch N for each file
php bin/migrate migrate                     # run all pending files as one batch
php bin/migrate rollback                    # revert the latest batch
php bin/migrate rollback 2                  # revert the latest two batches
php bin/migrate make "add email to users"   # create database/migrations/2026_..._add_email_to_users.php
```

`composer migrate -- <command>` does the same. The connection comes from `.ini` or the `DB_*` environment variables.

## A migration file

```php
return new class implements Migration {
    public function up(PDO $pdo): void {
        $pdo->exec('ALTER TABLE foo ADD COLUMN pinned TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(PDO $pdo): void {
        $pdo->exec('ALTER TABLE foo DROP COLUMN pinned');
    }
};
```

`down()` must undo `up()` exactly. Once a migration has run on a shared database, do not edit it; write a new one.

## How the runner works

1. **Find files.** `database/migrations/*.php`, sorted by name. The `YYYY_MM_DD_HHMMSS_` prefix makes name order equal creation order. A file with any other name is an error rather than being silently skipped.
2. **Compare with the `migrations` table.** It is created on first use and stores the file name, a batch number and when it ran. A recorded migration whose file has disappeared is reported as an error.
3. **Migrate.** Every pending file runs `up()` in name order. One `migrate` call is one batch, numbered `MAX(batch) + 1`. Each file is recorded right after it succeeds.
4. **Roll back.** The files of the latest batch run `down()` newest first, and their records are deleted.

## Failure and transactions

MySQL commits `CREATE`, `ALTER` and `DROP` immediately, even inside a transaction, so a migration cannot be undone by rolling back. The runner therefore:

- stops at the first failing file,
- does not record it, so it runs again next time,
- keeps the files that succeeded before it recorded,
- reports `Migration <name> failed during up(): <reason>`.

If the failed file changed something before the error (the first of two `CREATE TABLE` statements, for example), undo that part by hand, fix the file and run `migrate` again. Keeping one change per migration makes this rare.

## What a production tool adds

A locking table so two deploys cannot migrate at once, a schema builder that writes the SQL for you (`$table->string('email')`), dry runs that print the SQL, squashing old migrations into a schema dump, and transactional DDL on databases that support it, such as PostgreSQL.
