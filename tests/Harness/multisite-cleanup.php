<?php

// Removes the `momentumfr` site (and only that one). Run under bin/harness-lock.
use CraftCms\Cms\Support\Facades\Sites;

if ($fr = Sites::getSiteByHandle('momentumfr')) {
    Sites::deleteSite($fr);
}
echo Sites::getSiteByHandle('momentumfr') ? 'still there' : 'removed';
