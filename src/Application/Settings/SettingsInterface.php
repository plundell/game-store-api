<?php

declare(strict_types=1);

namespace App\Application\Settings;

interface SettingsInterface
{
    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key = '');

    //TODO: for type safety we should define multiple getters for different types
    //TODO: allow specifying a default value if setting isn't set, and throw if
    //      no default value is specified and setting isn't set
}
