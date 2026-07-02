<?php

echo '<pre>';

var_dump($_SERVER);

echo '\n\n';

if (function_exists('getallheaders')) {
	var_dump(getallheaders());
}
