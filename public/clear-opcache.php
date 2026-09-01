<?php

declare(strict_types=1);

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'OPcache cleared successfully!';
} else {
    echo 'OPcache is not enabled or opcache_reset is not available.';
}
