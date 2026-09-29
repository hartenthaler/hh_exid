<?php

declare(strict_types=1);

use Hartenthaler\Webtrees\Module\ExidModule\ExidModule;
use Fisharebest\Webtrees\Services\DataFixService;

require __DIR__ . '/src/autoload.php';
require __DIR__ . '/src/MoreI18N.php';
require __DIR__ . '/src/ExidModule.php';

return new ExidModule(new DataFixService());
