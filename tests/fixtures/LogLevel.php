<?php
namespace App;

use Enumerial\Enumerial;

class LogLevel extends Enumerial {
	protected static function sourceFile(): ?string {
		return __DIR__ . '/levels-default.json';
	}
}