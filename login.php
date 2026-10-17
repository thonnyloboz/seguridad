<?php
// Redirección hacia index.php (el nuevo login oficial)
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: index.php" . $queryString);
exit();