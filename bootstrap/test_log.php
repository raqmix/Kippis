<?php
$logs = file_get_contents('/Users/jamalahmad/Clients/Kippis/kippis-code/backend/storage/logs/laravel.log');
var_dump(substr($logs, -1000));
