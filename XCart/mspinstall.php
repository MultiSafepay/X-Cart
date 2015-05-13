<?php
 
require_once 'top.inc.php';
require_once 'top.inc.additional.php';
 
$path = realpath(dirname(__FILE__)).'/classes/XLite/Module/MultiSafepay/Ideal/install.yaml';
 
\XLite\Core\Database::getInstance()->loadFixturesFromYaml($path);


$path = realpath(dirname(__FILE__)).'/classes/XLite/Module/MultiSafepay/Visa/install.yaml';
 
\XLite\Core\Database::getInstance()->loadFixturesFromYaml($path);

$path = realpath(dirname(__FILE__)).'/classes/XLite/Module/MultiSafepay/Banktransfer/install.yaml';
 
\XLite\Core\Database::getInstance()->loadFixturesFromYaml($path);


$path = realpath(dirname(__FILE__)).'/classes/XLite/Module/MultiSafepay/Giropay/install.yaml';
 
\XLite\Core\Database::getInstance()->loadFixturesFromYaml($path);

$path = realpath(dirname(__FILE__)).'/classes/XLite/Module/MultiSafepay/Mistercash/install.yaml';
 
\XLite\Core\Database::getInstance()->loadFixturesFromYaml($path);

$path = realpath(dirname(__FILE__)).'/classes/XLite/Module/MultiSafepay/Mastercard/install.yaml';
 
\XLite\Core\Database::getInstance()->loadFixturesFromYaml($path);

$path = realpath(dirname(__FILE__)).'/classes/XLite/Module/MultiSafepay/Maestro/install.yaml';
 
\XLite\Core\Database::getInstance()->loadFixturesFromYaml($path);

echo 'done';exit;