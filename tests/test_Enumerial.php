<?php

require_once __DIR__ . '/../src/Enumerial.php';
require_once __DIR__ . '/fixtures/LogLevel.php';
require_once __DIR__ . '/fixtures/Role.php';

use App\LogLevel;
use App\Role;

echo "=== a) Both classes with source files passed to constructor ===\n";

$overrideLevels = new LogLevel( __DIR__ . '/fixtures/levels-override.json' );
foreach ( $overrideLevels->cases() as $case ) {
	echo "LogLevel (override): {$case->name} => {$case->value}\n";
}

$roles = new Role( __DIR__ . '/fixtures/roles.json' );
foreach ( $roles->cases() as $case ) {
	echo "Role (constructor):  {$case->name} => {$case->value}\n";
}

echo "\n=== b) Both classes without constructor parameter ===\n";

// LogLevel falls back to levels-default.json (works both instantiated and statically)
$defaultLevels = new LogLevel();
foreach ( $defaultLevels->cases() as $case ) {
	echo "LogLevel (default):  {$case->name} => {$case->value}\n";
}
echo 'Static call match:   ' . ( LogLevel::INFO() === $defaultLevels->INFO() ? 'true' : 'false' ) . "\n";
echo 'Override distinct:   ' . ( LogLevel::INFO() !== $overrideLevels->INFO() ? 'true' : 'false' ) . "\n";

// Role has no default sourceFile() and no constructor arg -> throws InvalidArgumentException
try {
	$broken = new Role();
} catch ( InvalidArgumentException $e ) {
	echo "Role() error:        {$e->getMessage()}\n";
}

try {
	Role::ADMIN();
} catch ( InvalidArgumentException $e ) {
	echo "Role::ADMIN() error: {$e->getMessage()}\n";
}
