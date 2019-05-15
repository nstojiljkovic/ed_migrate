<?php

return [
	'edmigration:create' => [
		'class' => \EssentialDots\EdMigrate\Command\CreateCommand::class
	],
	'edmigration:databasediff' => [
		'class' => \EssentialDots\EdMigrate\Command\DatabaseDiffCommand::class
	],
	'edmigration:migrate' => [
		'class' => \EssentialDots\EdMigrate\Command\MigrateCommand::class
	],
	'edmigration:partialmigration' => [
		'class' => \EssentialDots\EdMigrate\Command\PartialMigrationCommand::class
	],
	'edmigration:rollback' => [
		'class' => \EssentialDots\EdMigrate\Command\RollbackCommand::class
	],
	'edmigration:setextconfiguration' => [
		'class' => \EssentialDots\EdMigrate\Command\SetExtConfigurationCommand::class
	],
	'edmigration:status' => [
		'class' => \EssentialDots\EdMigrate\Command\StatusCommand::class
	],
];
