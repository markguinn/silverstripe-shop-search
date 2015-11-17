<?php
define('SHOP_SEARCH_PATH', dirname(__FILE__));
define('SHOP_SEARCH_FOLDER', basename(SHOP_SEARCH_PATH));

// If placeholder method exists, we're dealing with SS >= 3.2
define('SHOP_SEARCH_IS_SS32', method_exists('DB', 'placeholders'));
