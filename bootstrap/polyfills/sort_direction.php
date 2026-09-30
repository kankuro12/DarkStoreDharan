<?php

// The installed laravel/framework v13.33.0 release references a global-namespace
// `SortDirection` enum (see `use SortDirection;` in Illuminate\Support\Collection
// and Illuminate\Support\Arr) that doesn't actually ship in that package version,
// which crashes any Collection::sortBy()/sortByDesc() call. Polyfill it here until
// upstream ships a patched release.
if (! enum_exists('SortDirection')) {
    enum SortDirection
    {
        case Ascending;
        case Descending;
    }
}
