<?php
spl_autoload_register(function($class) {
	if (in_array($class, array(
		'LyteSocket',
	))) {
		require_once(dirname(__FILE__)."/lib/$class.php");
	}
});
