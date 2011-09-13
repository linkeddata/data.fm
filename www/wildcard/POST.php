<?php
/* POST.php
 * service HTTP POST controller
 *
 * $Id$
 */

if (isset($i_query)) {
    require_once('GET.php');
    exit;
}

// permissions
$acl_public = \sites\is_public_write($_domain);
if ($acl_public) {
} elseif (empty($_user)) {
    httpStatusExit(401, 'Unauthorized');
} elseif (!\sites\is_owner($_domain, $_user) && !wac('Write')) {
    httpStatusExit(403, 'Forbidden');
}

// action
$d = dirname($_filename);
if (!file_exists($d))
    mkdir($d, 0777, true);

$_data = file_get_contents('php://input');

if ($_input == 'raw') {
    file_put_contents($_filename, $_data, FILE_APPEND | LOCK_EX);
    exit;
}

$g = new \RDF\Graph($_options->sqlite ? 'sqlite' : '', $_filename, '', $_base);
if (!empty($_input) && $g->append($_input, $_data)) {
    $g->save();
} elseif ($_content_type == 'application/json' && $_SERVER['REQUEST_METHOD'] == 'PATCH') {
    if ($g->patch_array(json_decode($_data, 1)))
        $g->save();
} elseif ($_content_type == 'application/json') {
    if ($g->append_array(json_decode($_data, 1)))
        $g->save();
} else {
    httpStatusExit(406, 'Content-Type Not Acceptable');
}

@header('X-Triples: '.$g->size());
