<?php

// Throwaway: forces the FPM opcache to reset.
$result = opcache_reset();
echo $result ? 'opcache reset OK' : 'opcache reset failed';
