<?php
exit;

// START ARION FRAMEWORK
include __DIR__ . "/../../core/autoload.php";
new arion();

// crontab -e
//* * * * * php <path>/app/jobs/do-it.php

// START JOBS
#job::schedule('export', 'every 1min'); // = app/jobs/scripts/export.php
#job::schedule('points', '00:00');
#job::schedule('spill', '00:00');
job::schedule('price', 'every 1min');