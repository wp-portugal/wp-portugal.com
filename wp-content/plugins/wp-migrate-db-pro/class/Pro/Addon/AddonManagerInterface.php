<?php

namespace DeliciousBrains\WPMDB\Pro\Addon;

interface AddonManagerInterface {
    public function register();
    public function get_license_response_key();
}
