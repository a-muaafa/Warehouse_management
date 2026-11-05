<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add SoftDeletes to users so deleting a user never cascades or nulls append-only ledger rows
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        $driver = DB::getDriverName();

        foreach (['inventory_movements', 'activity_logs'] as $table) {
            match ($driver) {
                'mysql', 'mariadb' => $this->mysql($table),
                'pgsql' => $this->pgsql($table),
                'sqlite' => $this->sqlite($table),
                default => null,
            };
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        foreach (['inventory_movements', 'activity_logs'] as $table) {
            if (in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_UPDATE");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_DELETE");
            } elseif ($driver === 'pgsql') {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_append_only ON {$table}");
            }
        }
    }

    private function mysql(string $t): void
    {
        foreach (['UPDATE', 'DELETE'] as $op) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$t}_no_{$op}");
            DB::unprepared("
                CREATE TRIGGER {$t}_no_{$op} BEFORE {$op} ON {$t}
                FOR EACH ROW SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = '{$t} is append-only'
            ");
        }
    }

    private function pgsql(string $t): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_mutation() RETURNS trigger AS \$\$
            BEGIN RAISE EXCEPTION '% is append-only', TG_TABLE_NAME; END;
            \$\$ LANGUAGE plpgsql;
        ");
        DB::unprepared("DROP TRIGGER IF EXISTS {$t}_append_only ON {$t}");
        DB::unprepared("
            CREATE TRIGGER {$t}_append_only BEFORE UPDATE OR DELETE ON {$t}
            FOR EACH ROW EXECUTE FUNCTION prevent_mutation();
        ");
    }

    private function sqlite(string $t): void
    {
        foreach (['UPDATE', 'DELETE'] as $op) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$t}_no_{$op}");
            DB::unprepared("
                CREATE TRIGGER {$t}_no_{$op} BEFORE {$op} ON {$t}
                BEGIN SELECT RAISE(ABORT, '{$t} is append-only'); END;
            ");
        }
    }
};
