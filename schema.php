<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=maternity_system","root","");
foreach($pdo->query("DESCRIBE prenatal_visits") as $c) {
    print_r($c);
}
echo PHP_EOL . "---" . PHP_EOL;
foreach($pdo->query("DESCRIBE patients") as $c) {
    print_r($c);
}