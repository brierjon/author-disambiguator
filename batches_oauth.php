<?PHP

require_once ( __DIR__ . '/lib/initialize.php' ) ;
require_once ( __DIR__ . '/lib/wikidata_oauth.php' );

$oauth = new WD_OAuth('author-disambiguator', $oauth_ini_file);

$action = get_request ( 'action' , '' ) ;

$batch_id = get_request ( 'id' , '' ) ;

if ($action == 'authorize') {
	$oauth->doAuthorizationRedirect($oauth_url_prefix . 'batches_oauth.php');
	exit(0);
}

$dbtools = new DatabaseTools($db_passwd_file);
$db_conn = $dbtools->openToolDB('authors');

if ($action == 'stop') {
	$stop_id = get_request( 'batch_id', '' ) ;
	$batch = new Batch($stop_id);
	$batch->load($db_conn);
	if ($batch->is_running()) {
		$batch->stop();
		sleep(1);
	}
	header("Location: ?id=$batch_id");
	exit(0);
}

if ($action == 'restart') {
	$restart_id = get_request( 'batch_id', '' ) ;
	$batch = new Batch($restart_id);
	$batch->load($db_conn);
	if (! $batch->is_running()) {
		if ($oauth->isAuthOK()) {
			$batch->start($oauth);
			sleep(1);
		}
	}
	header("Location: ?id=$batch_id");
	$db_conn->close();
	exit(0);
}

if ($action == 'reset') {
	$reset_id = get_request( 'batch_id', '' ) ;
	$batch = new Batch($reset_id);
	$batch->load($db_conn);
	$batch->reset($db_conn);
	$db_conn->close();

	header("Location: ?id=$batch_id");
	exit(0);
}

if ($action == 'delete') {
	$delete_id = get_request( 'batch_id', '' ) ;
	if ($oauth->isAuthOK()) {
		$batch = new Batch($delete_id);
		$batch->load($db_conn);
		$batch->delete($oauth->userinfo->name, $db_conn);
	}
}

print disambig_header( True );

if ($oauth->isAuthOK()) {
	print "Wikimedia user account: " . $oauth->userinfo->name ;
	print " <span style='font-size:small'>(<a href='logout_oauth.php'>log out</a>)</a>";
} else {
	print "You haven't authorized this application yet: click <a href='?action=authorize'>here</a> to do that, then reload this page.";
}
print "<hr>";

$owner = $oauth->userinfo->name;

if ( $batch_id  == '') {
	$batch_list = Batch::batches_for_owner($db_conn, $owner);

	print "<table class='table table-striped table-condensed'><tr><th>Batch ID</th><th>Start time</th><th>Counts</th><th>Still processing?</th><th></th></tr>";
	foreach ($batch_list AS $batch) {
		$id = $batch->batch_id;
		print "<tr><td><a href='?id=$id'>$id</a></td>";
		print "<td>" . $batch->start_date . "</td>";
		$display_counts = array();
		$has_ready = 0;
		$has_error = 0;
		foreach ($batch->counts AS $status => $count) {
			$display_counts[] = "$status($count)";
			if ($status == 'READY' OR $status == 'RUNNING') {
				$has_ready = 1 ;
			} else if ($status == 'ERROR') {
				$has_error = 1;
			}
		}
		print "<td>" . implode($display_counts, ", ") . "</td>";

		if ( $batch->is_running() ) {
			print "<td>Yes</td>";
			print "<td><a href='?action=stop&batch_id=$id'>Stop batch?</a></td>";
		} else {
			print "<td>No</td>";
			if ($has_ready == 1) {
				print "<td><a href='?action=restart&batch_id=$id'>Restart batch?</a></td>";
			} else if ($has_error == 1) {
				print "<td><a href='?action=reset&batch_id=$id'>Reset errors?</a></td>";
			} else {
				print "<td><a href='?action=delete&batch_id=$id'>Delete batch?</a></td>";
			}
		}
		
		print "</tr>";
	}
	print "</table>";
} else {
	$batch = new Batch($batch_id);
	$batch->load($db_conn);
	print "<h3>Batch $batch_id started " . $batch->start_date . "</h3>\n";
	if ($batch->is_running()) {
		print("Still processing... ");
		print("<a href='?id=$batch_id&action=stop&batch_id=$batch_id'>Stop batch?</a>");
		print('<script type="text/javascript">
$(document).ready ( function () {
	setTimeout(function() { window.location.reload() }, 30000);
} ) ;
</script>');
	} else {
		print("Batch run ended");
	}

	$qids_by_ordinal = array();
	$qid_set = array();
	$dbquery = "SELECT ordinal, data from commands where batch_id = '$batch_id'";
	$results = $db_conn->query($dbquery);
	while ($row = $results->fetch_row()) {
		$ordinal = $row[0];
		$qids_by_ordinal[$ordinal] = array();
		$data = $row[1];
		$parts = preg_split('/:/', $data);
		foreach ($parts AS $data_part) {
			if (preg_match('/^Q\d+/', $data_part)) {
				$qids_by_ordinal[$ordinal][] = $data_part;
				$qid_set[$data_part] = 1;
			}
		}
	}
	$qid_labels = AuthorData::labelsForItems(array_keys($qid_set));

	$dbquery = "SELECT ordinal, action, status, message, run from commands where batch_id = '$batch_id' order by ordinal";
	$results = $db_conn->query($dbquery);

	print "<table class='table table-striped table-condensed'><tr><th>#</th><th>Timestamp</th><th>Action</th><th>Status</th><th>Message</th></tr>";
	while ($row = $results->fetch_row()) {
		$ordinal = $row[0];
		$action = $row[1];
		$qid_links = array();
		$qids = $qids_by_ordinal[$ordinal];
		foreach ($qids AS $qid) {
			$label = isset( $qid_labels[$qid] ) ? $qid_labels[$qid][0] : $qid;
			$qid_links[] = wikidata_link($qid, $label, ''); 
		}
		$action = $action . " for " . implode($qid_links, ", ");
		$status = $row[2];
		$message = $row[3];
		$run_timestamp = $row[4];
		print "<tr><td>$ordinal</td>";
		print "<td>$run_timestamp</td>";
		print "<td>$action</td>";
		print "<td>$status</td>";
		print "<td>$message</td>";
		print "</tr>\n";
	}
	print "</table>";
	$results->close();
}
$db_conn->close();

print_footer() ;

?>
