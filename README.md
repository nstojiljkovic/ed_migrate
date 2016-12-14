# ed_migrate

Extension ed_migrate allows seamless migration of any TYPO3 content (DB, files). It is proven production
ready for migration of TYPO3 websites from version 4.5 to 6.2 and 7.6.

It is compatible with TYPO3 versions 6.2 and 7.6.

The approach to migrations is that instead of having one migration script for a complete website, 
each of the extensions can defined their content migrations accross versions. If you need a more general
migration of a content (in case when the extensions are completely replaced with other extensions), you
can define those in a separate TYPO3 extension.

Every extension defines its migrations in a migration namespace, which is a regular PHP namespace.

The migrations can be written in both imperative and declarative style (where each migration creates 
its own AST run by the `EssentialDots\EdMigrate\Service\MigrationService`). The declarative style is 
preferred as it automatically runs the commands using TYPO3 core's functions (and famous `DataHandler`)
and allows for seamless multi-threaded execution.

### License

**The extension is licensed under the same GPL license as TYPO3 core. This software is FOSS.**

**Please star the project if you like it, and contribute via pull requests and/or donations if you
use it.**

## CLI commands

Each of the CLI command has namespace parameter.

###  Status command

The status command prints a list of all migrations, along with their current status. You can use this 
command to determine which migrations have been run.

```bash
    $ typo3/cli_dispatch.phpsh extbase edmigration:status \
        --namespace 'EssentialDots\Downloads\Migration'
```

### Migrate command

The migrate command runs all of the available migrations, optionally up to a specific version:

```bash
    $ typo3/cli_dispatch.phpsh extbase edmigration:migrate \
        --namespace 'EssentialDots\Downloads\Migration'
```

To migrate to a specific version then use the `--target` parameter:

```bash
    $ typo3/cli_dispatch.phpsh extbase edmigration:migrate \
        --namespace 'EssentialDots\Downloads\Migration' \
        --target 20151110113000
```

### Rollback command

The rollback command is used to undo previous migrations. It is the opposite of the migrate command.

You can rollback the previous migration by using the rollback command with no target argument:

```bash
    $ typo3/cli_dispatch.phpsh extbase edmigration:rollback \
        --namespace 'EssentialDots\Downloads\Migration' \
```

You can also specify what migration to rollback to:

```bash
    $ typo3/cli_dispatch.phpsh extbase edmigration:rollback \
        --namespace 'EssentialDots\Downloads\Migration' \
        --target 20151110113000
```

### Database "diff" command

The databaseDiff command prints a list of database differences between SQL files across TYPO3 
extensions and actual database state.

```bash
    $ typo3/cli_dispatch.phpsh extbase edmigration:databaseDiff \
        --addRemovalQueries=0
```

Parameter addRemovalQueries is optiona. If set to 1, the databaseDiff command will print the 
`DROP TABLE` and `DROP COLUMN` queries as well.

### Create command

The create command is used to create a new migration file.

```bash
    $ typo3/cli_dispatch.phpsh extbase edmigration:create \
        --namespace 'EssentialDots\Downloads\Migration' \
        --migrationName 'BeGroups' \
        --addRemovalQueries=0
```

The content of the file will be populated with the output of databaseDiff command. 
The created migration file should be regarded just as a kickstart. By default it does 
not provide rollback, you have to implement it on your own.

Parameters `namespace` and `migrationName` are required. If the folder for namespace does 
not exist, it will be automatically created.

The create command should be used only during development.

### Partial migration command

**WARNING**: Partial migrations are to be run only during development. Do not run 
partial migrations on production environment!

During the development, it can come in handy to debug migrations which implement the 
`PageRecursiveMigrationInterface` interface. Implementing this interface enables 
multi-threaded processing of the migrations (in order to overcome the memory 
limitations of the core’s `DataHandler` class and in order to improve the migration speed).


For those migrations, you can run partial migration on only selected pages, without 
updating the status.

For example, to migrate pages `102` and `110` using `Migration20151110132000ColumnPosition` 
you can run:

```bash
    $ typo3/cli_dispatch.phpsh extbase edmigration:partialmigration \
        --migration 'EssentialDots\EssentialDotsWebsite\Migration\Migration20151110132000ColumnPosition' \
        --action up \
        --pageIds 102,110
```

## Tips

**During the development, always try the rollback command.**

## TODO

* Write documentation on how the actual advanced migrations should be written (using expressions) for 
various real-life scenarios.