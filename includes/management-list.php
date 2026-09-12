<?php
require_once __DIR__.'/portal-layout.php';
function management_list_query($db,$sql,$columns,$order) {
    $page=portal_page($_GET);$search=is_string($_GET['q']??null)?substr(trim($_GET['q']),0,100):'';
    foreach([...$columns,$order] as $column)if(!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D',$column))throw new LogicException('Invalid list column.');
    $q=$db->prepare('SELECT * FROM ('.$sql.') records WHERE CONCAT_WS(" ",'.implode(',',$columns).') LIKE :list_search ORDER BY '.$order.' LIMIT 26 OFFSET '.(($page-1)*25));
    $q->bindValue(':list_search','%'.$search.'%');return $q;
}
function management_list_controls() {
    $page=portal_page($_GET);$search=is_string($_GET['q']??null)?substr($_GET['q'],0,100):'';
    echo '<form method="get" class="form-inline"><label for="list-search">Search all records</label> <input id="list-search" class="form-control" name="q" value="'.academic_h($search).'" maxlength="100"> <button class="btn btn-default">Search</button></form>';
    portal_pagination($page,!empty($GLOBALS['management_list_has_next']),['q'=>$search]);echo '<p class="help-block">25 records per page. Table sorting applies to the current page.</p>';
}
