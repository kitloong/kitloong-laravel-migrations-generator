<?php

namespace KitLoong\MigrationsGenerator\Support;

use Illuminate\Support\Facades\App;

trait CheckLaravelVersion
{
    public function atLeastLaravel12(): bool
    {
        return $this->atLeastLaravelVersion('12.0') || version_compare(App::version(), '12.x-dev', '==');
    }

    private function atLeastLaravelVersion(string $version): bool
    {
        return version_compare(App::version(), $version, '>=');
    }
}
