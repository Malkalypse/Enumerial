# Enumerial

An abstract PHP base class for creating **enum-like structures backed by external serialized files** (JSON by default).

Unlike native PHP `enum` cases—which must be hardcoded at compile time—`Enumerial` lets you define cases in data files so users or downstream developers can extend or override them without touching PHP source code.

<br>

## Features

**Familiar `BackedEnum`-style API:**
- `cases()`
- `from()`
- `tryFrom()`
- `tryFromName()`
- `readonly`, `$name`, and `$value` properties.

<br>

**Strict identity & `match` support:**

Case instances are cached per class and source file, so `===` comparisons and `match` expressions work just like native enums.

<br>

**Default + per-instance overrides:**

Subclasses can define a default `sourceFile()`, which callers can supersede per instance via the constructor—or omit entirely to require a runtime path.

<br>

**Extensible loader:**

Override `load()` in any subclass to transform values or support other formats (INI, PHP arrays, YAML, etc.).

<br>

## Requirements

- PHP 8.1+ (uses `readonly` properties and `ValueError`)

<br>

## How To Use It

### 1. Create a JSON source file

```json
{
	"INFO": "info",
	"WARN": "warning",
	"ERROR": "error"
}
```

### 2. Create a class extending `Enumerial`

```php
<?php
namespace App;

use Enumerial\Enumerial;

class LogLevel extends Enumerial {
	protected static function sourceFile(): ?string {
		return __DIR__ . '/levels-default.json';
	}
}
```

### 3. Access cases

```php
/* Demonstrate usage of the LogLevel enum-like class */

require_once __DIR__ . '/../src/Enumerial.php';
require_once __DIR__ . '/fixtures/LogLevel.php';

use App\LogLevel;


/* Access name and value */

$level = LogLevel::ERROR();

echo $level->name . PHP_EOL;
// ERROR

echo $level->value . PHP_EOL;
// error


/* Use match() with enum values */

$prefix = match ( $level ) {
	LogLevel::ERROR() => '[ERR]',
	LogLevel::WARN()  => '[WRN]',
	default           => '[INF]',
};
echo $prefix . PHP_EOL;
// [ERR]


/* Update $level to INFO and test match() again */

$level = LogLevel::INFO();
$prefix = match ( $level ) {
	LogLevel::ERROR() => '[ERR]',
	LogLevel::WARN()  => '[WRN]',
	default           => '[INF]',
};
echo $prefix . PHP_EOL;
// [INF]


/* Strict equality and match expressions work natively */

echo( LogLevel::ERROR() === LogLevel::ERROR() ? 'true' : 'false' ) . PHP_EOL;
// true

echo( LogLevel::ERROR() === LogLevel::INFO() ? 'true' : 'false' ) . PHP_EOL;
// false


/* Enum-style helpers */

/* Get all cases */

$cases = [];
foreach( LogLevel::cases() as $case ) {
	$cases[] = "{$case->name} => {$case->value}";
}
echo implode( ', ', $cases ) . PHP_EOL;
// INFO => info, WARN => warning, ERROR => error


/* Lookup by value */

$from = LogLevel::from( 'info' );
echo "{$from->name} => {$from->value}\n";
// INFO => info

$tryFrom = LogLevel::tryFrom( 'warning' );
echo "{$tryFrom->name} => {$tryFrom->value}\n";
// WARN => warning


/* Lookup by name */

$tryFromName = LogLevel::tryFromName( 'ERROR' );
echo "{$tryFromName->name} => {$tryFromName->value}\n";
// ERROR => error


/* Invalid lookups using tryFrom() and tryFromName() return null */

echo LogLevel::tryFromName( 'NOPE' ) . "\n";
// (prints nothing; null)

echo LogLevel::tryFrom( 'other' ) . "\n";
// (prints nothing; null)


/* Invalid lookups using from() throw ValueError */

$missing = LogLevel::from( 'missing' );
// Fatal error: Uncaught ValueError: "missing" is not a valid backing value for App\LogLevel...
```

<br>

## Overriding & Dynamic Source Files

### Overriding a subclass's default file

Callers can pass a custom file path to the constructor to supersede `sourceFile()` for that instance (without affecting static calls or other instances):

```php
$customLevels = new LogLevel( __DIR__ . '/levels-override.json' );

$customLevels->ERROR(); // Uses levels-override.json
LogLevel::ERROR();      // Still uses levels-default.json
```

Case instances returned by `$customLevels` stay bound to `levels-override.json`, and two instances pointed at the same file share the same cached case objects (`===` remains `true`).

### Subclasses without a default file

Subclasses are **not** required to implement `sourceFile()`. If omitted, a path must be provided to the constructor:

```php
class Role extends Enumerial {}

// Works when a file is passed to the constructor
$roles = new Role( __DIR__ . '/roles.json' );
echo $roles->ADMIN()->value;

// Throws InvalidArgumentException when neither constructor nor sourceFile() provides a path
new Role();
Role::ADMIN();
```

## Custom Loaders & Value Transformation

Subclasses can override `load()` to transform values after decoding, or to parse a format other than JSON:

```php
class Consolor extends Enumerial {
	protected static function sourceFile(): ?string {
		return __DIR__ . '/colors.json';
	}

	protected static function load( string $sourceFile ): array {
		return array_map(
			fn( string|int $code ): string => "\033[{$code}m",
			parent::load( $sourceFile )
		);
	}

	public function apply( string $text ): string {
		$reset = $this->tryFromName( 'DEFAULT' )?->value ?? "\033[0m";
		return $this->value . $text . $reset;
	}
}
```

> ***Note:*** Inside instance methods, use `$this->tryFromName()` rather than `self::tryFromName()`.
> This way the lookup uses the overridden source file if provided.

<br>

## API Reference

| Member | Description |
| :--- | :--- |
| `__construct()` | Loads the given file, or falls back to `static::sourceFile()`. Throws `InvalidArgumentException` if neither is set. |
| `$case->name` | `readonly string` case key from the source file. |
| `$case->value` | `readonly string\|int` backing value from the source file. |
| `cases()` | Returns an array of all case instances for the active source file. |
| `from()` | Looks up a case by backing value; throws `ValueError` if not found. |
| `tryFrom()` | Looks up a case by backing value; returns `null` if not found. |
| `tryFromName()` | Looks up a case by key name; returns `null` if not found. |
| `<CASE_NAME>()` | Magic lookup (`__call` / `__callStatic`) returning the cached case instance; throws `ValueError` if undefined. |

*All lookup methods (`cases`, `from`, `tryFrom`, `tryFromName`, and `<CASE_NAME>()`) work both statically (using `static::sourceFile()`) and on an instantiated object (using its resolved file).*

<br>

## Caveats vs. Native PHP Enums

**Method syntax:**

Cases are retrieved as method calls (`LogLevel::ERROR()`), not constants (`LogLevel::ERROR`).

<br>

**Interface compatibility:**

PHP does not allow userland classes to `implements \BackedEnum` or `\UnitEnum`.

<br>

**IDE autocomplete:**

Add `@method static self CASE_NAME()` docblocks on subclasses if you want autocomplete for known default keys.

<br>

**Serialization:**

Unserializing a cached case instance creates a new object in memory, which breaks `===` identity; store `$case->value` or `$case->name` and rehydrate via `from()` / `tryFromName()` instead.