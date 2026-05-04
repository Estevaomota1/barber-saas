<?php
$file = file_get_contents('tests/Feature/ClientTest.php');
$file = preg_replace('/\[[^\]]+\]\(mailto:[^)]+\)/', 'barbearia{\}@teste.com', $file);
file_put_contents('tests/Feature/ClientTest.php', $file);
echo 'done';
