<?php
namespace Enumerial;

use InvalidArgumentException; // throw errors on invalid argument
use ValueError;								// throw errors on invalud value
use ReflectionClass;					// create instances without calling constructor

/** Base class for enum-like types backed by a serialized source file
 * - Subclasses may define a default path via sourceFile()
 * - Callers can supersede per instance by passing a custom path to __construct()
 * @method static static[] cases()
 * @method static static from(string|int $value)
 * @method static static|null tryFrom(string|int $value)
 * @method static static|null tryFromName(string $name)
 */
abstract class Enumerial {

	/** @var array<string, array<string, string|int>> */
	private static array $definitions = []; // store subclass definitions

	/** @var array<string, array<string, static>> */
	private static array $instances = [];	// store subclass instances

	private string $sourceFile;					// path to instance source file
	public readonly string			$name;	// name of enum case
	public readonly string|int	$value;	// value of enum case

	/** Ensure source file is loaded
	 * @param string|null $sourceFile Path to source file (supersedes static::sourceFile())
   * @throws InvalidArgumentException If no source file provided
   */
	public function __construct( ?string $sourceFile = null ) {
		$this->sourceFile = self::resolveSourceFile( $sourceFile ); // resolve source file path
		self::ensureLoaded( $this->sourceFile );                    // load definitions
	}

	/** Dispatch to appropriate internal method based on method name (instance) */
	public function __call( string $name, array $arguments ): mixed {
		return self::dispatch( $name, $arguments, $this->sourceFile );
	}

	/** Dispatch to appropriate internal method based on method name (static) */
	public static function __callStatic( string $name, array $arguments ): mixed {
		return self::dispatch( $name, $arguments, self::resolveSourceFile() );
	}

	// #region Source File Resolution
	/** Resolve the path to the source file
	 * @param string|null $override Optional override path provided to constructor
	 * @return string Path to the source file
	 */
	private static function resolveSourceFile( ?string $override = null ): string {
		$file = $override ?? static::sourceFile(); // use override if provided, otherwise call sourceFile()

		// If no source file is provided, throw an exception
		if( $file === null || $file === '' ) {
			throw new InvalidArgumentException(
				sprintf( '%s requires a source file via constructor or sourceFile().', static::class )
			);
		}

		return $file;
	}
	/** Path to source file
	 * - Subclasses can override this with a default path
	 * - Can be omitted from subclasses if the constructor always provides a path
	 * @return string|null Path to source file (or null if not defined)
	 */
	protected static function sourceFile(): ?string {
		return null;
	}
	// #endregion Source File Resolution

	// #region Dispatching Methods
	/** Dispatch method calls to appropriate internal methods
	 * @param string	$name				Name of method being called
	 * @param array		$arguments	Arguments passed to method
	 * @param string	$sourceFile Path to source file
	 * @return mixed Result of dispatched method call
	 */
	private static function dispatch( string $name, array $arguments, string $sourceFile ): mixed {
		return match ( $name ) {
			'cases'       => self::getCases( $sourceFile ),
			'from'        => self::getFrom( $arguments[0], $sourceFile ),
			'tryFrom'     => self::getTryFrom( $arguments[0], $sourceFile ),
			'tryFromName' => self::getTryFromName( $arguments[0], $sourceFile ),
			default       => self::getCase( $name, $sourceFile ),
		};
	}
	/** Return all defined cases for subclass
	 * @return static[] array of subclass instances
	 */
	private static function getCases( string $sourceFile ): array {
		$key = self::ensureLoaded( $sourceFile );

		return array_map(
			fn( string $name ) => self::getCase( $name, $sourceFile ),
			array_keys( self::$definitions[$key] )
		);
	}
	/** Get instance of enum from backing value (throws ValueError if invalid)
	 * @param string|int	$value			Backing value
	 * @param string			$sourceFile	Path to the source file
	 * @return static The enum instance
	 * @throws ValueError If the value is not valid
	 */
	private static function getFrom( string|int $value, string $sourceFile ): static {
		return self::getTryFrom( $value, $sourceFile )
			?? throw new ValueError( sprintf( '"%s" is not a valid backing value for %s', $value, static::class ) );
	}
	/** Get instance of enum from name (or null if not found)
	 * @param string	$name				Enum case name
	 * @param string	$sourceFile	Path to source file
	 * @return static|null Enum instance or null if not found
	 */
	private static function getTryFromName( string $name, string $sourceFile ): ?static {
		$key = self::ensureLoaded( $sourceFile );

		return array_key_exists( $name, self::$definitions[$key] )
			? self::getCase( $name, $sourceFile )
			: null;
	}

	/** Get instance of enum from backing value (or null if invalid)
	 * @param string|int	$value			Backing value
	 * @param string			$sourceFile	Path to the source file
	 * @return static|null The enum instance or null if not found
	 */
	private static function getTryFrom( string|int $value, string $sourceFile ): ?static {
		$key  = self::ensureLoaded( $sourceFile );
		$name = array_search( $value, self::$definitions[$key], true );

		return $name !== false ? self::getCase( $name, $sourceFile ) : null;
	}
	// #endregion Dispatching Methods

	/** Get instance of enum from name
	 * @param string $name				Enum case name
	 * @param string $sourceFile	Path to source file
	 * @return static Enum instance
	 * @throws ValueError If case is not defined
	 */
	private static function getCase( string $name, string $sourceFile ): static {
		$key = self::ensureLoaded( $sourceFile );

		// Return existing instance if already created
		if( isset( self::$instances[$key][$name] ) ) {
			return self::$instances[$key][$name];
		}

		// Check if case is defined in subclass definitions
		if( !array_key_exists( $name, self::$definitions[$key] ) ) {
			throw new ValueError( sprintf( 'Undefined case %s::%s', static::class, $name ) );
		}

		// Create new instance without calling constructor
		$case							= ( new ReflectionClass( static::class ) )->newInstanceWithoutConstructor();
		$case->sourceFile	= $sourceFile;
		$case->name				= $name;
		$case->value			= self::$definitions[$key][$name];

		return self::$instances[$key][$name] = $case; // cache and return new instance
	}

	// #region Loading Definitions
	/** Ensure subclass definitions are loaded */
	private static function ensureLoaded( string $sourceFile ): string {
		$key = self::cacheKey( $sourceFile ); // unique key for subclass + source file

		// Load definitions if not already loaded
		if ( !isset( self::$definitions[$key] ) ) {
			self::$definitions[$key] = static::load( $sourceFile );
		}

		return $key; // return unique key for subclass + source file
	}
	/** Generate a unique key for caching subclass definitions */
	private static function cacheKey( string $sourceFile ): string {
		return static::class . "\0" . $sourceFile;
	}
	/** Load subclass definitions from JSON file */
	protected static function load( string $sourceFile ): array {
		$json = file_get_contents( $sourceFile );                     // read JSON file
		return json_decode( $json, true, 512, JSON_THROW_ON_ERROR );  // decode JSON into associative array
	}
	// #endregion Loading Definitions
}