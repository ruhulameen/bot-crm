<?php

return [

    /*
    | A device is "online" when its last heartbeat is newer than this many minutes.
    | Bots should send a heartbeat roughly every minute.
    */
    'online_threshold_minutes' => (int) env('LICENSE_ONLINE_THRESHOLD_MINUTES', 3),

    /*
    | Licenses expiring within this many days are flagged as "expiring soon".
    */
    'expiring_soon_days' => (int) env('LICENSE_EXPIRING_SOON_DAYS', 7),

];
